<?php

namespace GP\Oxid\IdeHelper\Command;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ModuleConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ShopConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ActiveModulesDataProviderInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Install\Service\ModuleConfigurationInstallerInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Service\ModuleActivationServiceInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\State\ModuleStateService;
use OxidEsales\EshopCommunity\Internal\Framework\Module\State\ModuleStateServiceInterface;
use OxidEsales\EshopCommunity\Internal\Transition\Utility\ContextInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IdeHelper extends Command {
    protected $shopIds;
    protected InputInterface $input;
    protected OutputInterface $output;

    public function __construct(
        protected ShopConfigurationDaoInterface $shopConfigurationDao,
        protected ContextInterface $context,
        protected ModuleStateServiceInterface $moduleStateService,
        protected ModuleActivationServiceInterface $moduleActivationService,
        protected ModuleConfigurationInstallerInterface $moduleConfigurationInstaller,
        protected ModuleConfigurationDaoInterface $moduleConfigurationDao,
        protected QueryBuilderFactoryInterface $queryBuilderFactory,
        protected ActiveModulesDataProviderInterface $modProvider
    ) {
        parent::__construct(null);
    }
    protected function configure() {
        $this->setName('gp:ide-helper');
        $this->setDescription('Create IDE-Helper file');
        $help = <<<EOT
    Apply module activation/deactivation by given state through configuration.
EOT;

        $this->setHelp($help);
        
    }
    
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->input = $input;
        $this->output = $output;
        $shopIds = $this->getSelectedShopIds();
        $mods = $this->modProvider->getClassExtensions();
        $output->writeln(print_r($mods, true));
        return 0;
    }


    protected function getSelectedShopIds() {
        try {
            return $this->shopIds ??= [ (int) $this->input->getOption('shop-id')];
        } catch (\InvalidArgumentException $e) {
            return $this->shopIds ??= $this->context->getAllShopIds();
        } 
    }
}