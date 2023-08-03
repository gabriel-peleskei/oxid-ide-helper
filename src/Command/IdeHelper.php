<?php declare(strict_types=1);

namespace GP\Oxid\IdeHelper\Command;

use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ShopConfigurationDaoInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IdeHelper extends Command {
    protected ?int $shopId = null;
    protected InputInterface $input;
    protected OutputInterface $output;

    protected function configure() {
        $this->setName('gp:ide:helper');
        // $this->addOption('');
        $this->setDescription('Create IDE-Helper file');
        $help = <<<EOT
<info>Create <fg=gray;options=bold>.ide-helper.php</> File withing <fg=gray;options=bold>/source/modules</> directory for the given shop.</info>
If you have a multishop system, use <comment>--shop-id=2</comment> to define the shop's module chain.

<comment>Notice:</comment>
Only <fg=gray>installed</> and <fg=gray>known</> class extensions are considered. 
Basically, it is the representation of the <fg=gray>var/configuration/shops/<SHOP_ID>/class_extension_chain.yaml</> file.


EOT;

        $this->setHelp($help);
        
    }

    
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->input = $input;
        $this->output = $output;
        $shopId = $this->getShopId();
        $chain = $this->getChain($shopId);
        $extensions = [];
        foreach ($chain as $parent => $items) {
            // track last class of chain
            $last = null;
            foreach ($items as $new) {
                $extensions[] = $this->getExtension($last ?? $parent, $new);
                $last = $new;
            }
        }
        $filePath = $this->getModulesDir('.ide-helper.php');
        $output->writeln("Writing helper file for <comment>shop-id</comment>=<comment>{$shopId}</comment> to <comment>{$filePath}</comment>");
        if ($this->makeFile($extensions, $filePath)) {
            $output->writeln("<info>File has been created</info>");
        } else {
            $output->writeln('<error>File could not be written</error>');
        }
        return 0;
    }


    protected function getShopId(): int {
        try {
            return $this->shopId ??= (int) $this->input->getOption('shop-id');
        } catch (\InvalidArgumentException $e) {
            return $this->shopId ??= 1;
        } 
    }

    protected function getConfiguration6(int $shopId): ?array {
        return ContainerFactory::getInstance()->getContainer()->get(ShopConfigurationDaoInterface::class)->get($shopId)->getClassExtensionsChain()->getChain();
    }

    protected function getChain(int $shopId): array {
        return ContainerFactory::getInstance()->getContainer()->get(ShopConfigurationDaoInterface::class)->get($shopId)->getClassExtensionsChain()->getChain();
    }

    protected function getModulesDir(string ... $path): string {
        return implode(DIRECTORY_SEPARATOR, [OX_BASE_PATH, 'modules', ...$path]);
    }

    protected function getExtension(string $parent, string $new): string {
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

    protected function makeFile(array $paths, string $filePath): bool {
        $contents = implode("\n", $paths);
        $result = file_put_contents($filePath, "<?php // IDE Helper for shop {$this->shopId}\n{$contents}");
        if (false === $result) {
            return false;
        }
        return true;
    }
}