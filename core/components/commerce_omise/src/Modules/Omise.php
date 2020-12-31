<?php
namespace DigitalPenguin\Commerce_Omise\Modules;

use modmore\Commerce\Admin\Configuration\About\ComposerPackages;
use modmore\Commerce\Admin\Sections\SimpleSection;
use modmore\Commerce\Events\Admin\PageEvent;
use modmore\Commerce\Events\Gateways;
use modmore\Commerce\Modules\BaseModule;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Omise extends BaseModule {

    public function getName()
    {
        $this->adapter->loadLexicon('commerce_omise:default');
        return $this->adapter->lexicon('commerce_omise');
    }

    public function getAuthor()
    {
        return 'Murray Wood';
    }

    public function getDescription()
    {
        return $this->adapter->lexicon('commerce_omise.description');
    }

    public function initialize(EventDispatcher $dispatcher)
    {
        // Load our lexicon
        $this->adapter->loadLexicon('commerce_omise:default');

        // Add template path to twig
        $root = dirname(__DIR__, 2);
        $this->commerce->view()->addTemplatesPath($root . '/templates/');

        // Add composer libraries to the about section (v0.12+)
        $dispatcher->addListener(\Commerce::EVENT_DASHBOARD_LOAD_ABOUT, [$this, 'addLibrariesToAbout']);

        // Register the gateway
        $dispatcher->addListener(\Commerce::EVENT_GET_PAYMENT_GATEWAYS, [$this, 'addGateway']);
    }

    /**
     * @param Gateways $event
     */
    public function addGateway(Gateways $event)
    {
        $event->addGateway(\DigitalPenguin\Commerce_Omise\Gateways\Omise::class, $this->adapter->lexicon('commerce_omise.gateway'));
        $event->addGateway(\DigitalPenguin\Commerce_Omise\Gateways\PromptPay::class, $this->adapter->lexicon('commerce_omise.omise_promptpay'));

    }

    public function getModuleConfiguration(\comModule $module)
    {
        return [];
    }

    public function addLibrariesToAbout(PageEvent $event)
    {
        $lockFile = dirname(__DIR__, 2) . '/composer.lock';
        if (file_exists($lockFile)) {
            $section = new SimpleSection($this->commerce);
            $section->addWidget(new ComposerPackages($this->commerce, [
                'lockFile' => $lockFile,
                'heading' => $this->adapter->lexicon('commerce.about.open_source_libraries') . ' - ' . $this->adapter->lexicon('commerce_omise'),
                'introduction' => '', // Could add information about how libraries are used, if you'd like
            ]));

            $about = $event->getPage();
            $about->addSection($section);
        }
    }
}
