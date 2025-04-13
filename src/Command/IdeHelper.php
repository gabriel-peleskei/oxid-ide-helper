<?php

declare(strict_types=1);

namespace GP\Oxid\IdeHelper\Command;

use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\Chain\ClassExtensionsChainDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ShopConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject\ModuleConfiguration\ClassExtension;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Bridge\ModuleActivationBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Transition\Utility\ContextInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

class IdeHelper extends Command
{
    protected InputInterface $input;
    protected OutputInterface $output;

    /**
     * @param null|array<string,ModuleConfiguration> $list
     */
    public function __construct(
        protected ClassExtensionsChainDaoInterface $chainProvider,
        protected ContextInterface $context,
        protected ModuleActivationBridgeInterface $module,
        protected ShopConfigurationDaoInterface $conf,
        protected ?array $list = null,
    ) {
        parent::__construct(null);
        $this->list = $this->list ?? $this->conf->get($this->getShopId())->getModuleConfigurations();
    }
    protected function configure(): void
    {
        $this->setName('gp:ide:helper');
        // $this->addOption('');
        $this->setDescription('Create IDE-Helper file');
        $help = <<<EOT
<info>Creates <fg=gray;options=bold>.ide-helper.php</> file within <fg=gray;options=bold>/source/modules</> directory for the given shop.</info>
If you have a multishop system, use <comment>--shop-id=2</comment> to define the shop's module chain.

<comment>Notice:</comment>
Only <fg=gray>installed</> and <fg=gray>known</> class extensions are considered. 
Basically, it is the representation of the <fg=gray>var/configuration/shops/<SHOP_ID>/class_extension_chain.yaml</> file.

If you want to only filter <fg=green>active</> modules, use <fg=cyan>--active</> or <fg=cyan>-a</> flag.
Should the file be put into shop-root instead, use <fg=cyan>--root</> or <fg=cyan>-r</> flag.


EOT;

        $this->setHelp($help);
        $this->addOption("active", "a", InputOption::VALUE_NONE, "Only consider active modules");
        $this->addOption("root", "r", InputOption::VALUE_NONE, "Put file to root instead source/modules/");
    }

    protected function isActiveOption(InputInterface $in): bool {
        return (bool) $in->getOption("active");
    }

    protected function isRootOption(InputInterface $in): bool {
        return (bool) $in->getOption("root");
    }

    /**
     * This method is used to find module by qualified class name
     * @param string $ext fully qualified class name
     * @return null|array{string, string}
     */
    protected function findExtension(string $ext): ?array {
        /**
         * @var string $id
         * @var ModuleConfiguration $info
         */
        foreach($this->list as $id => $info) {
            $chain = $info->getClassExtensions();
            /** @var ClassExtension $item */
            foreach($chain as $item) {
                if ($ext === $item->getModuleExtensionClassName()) {
                    return [$id, $item->getModuleExtensionClassName()];
                }
            }
        }
        return null;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $list =  $this->conf->get($this->getShopId())->getModuleConfigurations();
        $this->input = $input;
        $this->output = $output;
        $shopId = $this->getShopId();
        $chain = $this->getChain($shopId);
        $active = $this->isActiveOption($input);
        $extensions = [];
        foreach ($chain as $parent => $items) {
            // track last class of chain
            $last = null;
            foreach ($items as $new) {
                if( $active) {
                    if ($match = $this->findExtension($new)) {
                        $modId = $match[0];
                        if (!$this->module->isActive($modId, $shopId)){
                            continue;
                        }
                    }
                }
                $extensions[] = $this->getExtension($last ?? $parent, $new);
                $last = $new;
            }
        }
        $filePath = $this->getModulesDir('.ide-helper.php');
        if ($this->isRootOption($input)) {
            $filePath = $this->getRootDir(".ide-helper.php");
        }
        $count = count($extensions);
        $output->writeln("Writing $count extensions to helper file for <comment>shop-id</comment>=<comment>{$shopId}</comment> to <comment>{$filePath}</comment>");
        if ($this->makeFile($extensions, $filePath)) {
            $output->writeln("<info>File has been created</info>");
        } else {
            $output->writeln('<error>File could not be written</error>');
        }
        return 0;
    }

    protected function getShopId(): int
    {
        try {
            return $this->context->getCurrentShopId();
        } catch (\InvalidArgumentException) {
            return 1;
        }
    }

    protected function getChain(int $shopId): array
    {
        return $this->chainProvider->getChain($shopId)->getChain();
    }

    /**
     * @paran string... $path 
     * @return string 
     */
    protected function getModulesDir(string ...$path): string
    {
        return Path::join($this->context->getSourcePath(), 'modules', ...$path);
    }

    /**
     * @paran string... $path 
     * @return string 
     */
    protected function getRootDir(string ... $path): string {
        return Path::join($this->context->getShopRootPath(), ...$path);
    }

    /**
     * @param string $parent class with namespace from parent scope
     * @param string $new class to extend from parent
     * @return string namespace for *class*_parent override
     */
    protected function getExtension(string $parent, string $new): string
    {
        $namespaceList = explode('\\', $new);
        $className = array_pop($namespaceList);
        $namespace = implode('\\', $namespaceList);
        if (false === strpos($new, '\\')) {
            $tmp = explode('/', $new);
            $className = array_pop($tmp);
        }
        if (false === strpos($parent, '\\')) {
            $tmp = explode('/', $parent);
            $extends = "\\" . array_pop($tmp);
        } else {
            $extends = "\\$parent";
        }
        return "namespace $namespace { class {$className}_parent extends $extends {} }";
    }
    /**
     * @param array<int,string> $paths list of namespaces
     * @return bool
     */
    protected function makeFile(array $paths, string $filePath): bool
    {
        $contents = implode("\n", $paths);
        $result = file_put_contents($filePath, "<?php // IDE Helper for shop {$this->shopId}\n{$contents}");
        if (false === $result) {
            return false;
        }
        return true;
    }
}

