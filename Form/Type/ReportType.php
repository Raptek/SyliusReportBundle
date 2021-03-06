<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\ReportBundle\Form\Type;

use Sylius\Bundle\ReportBundle\Form\EventListener\BuildReportDataFetcherFormSubscriber;
use Sylius\Bundle\ReportBundle\Form\EventListener\BuildReportRendererFormSubscriber;
use Sylius\Bundle\ReportBundle\Form\Type\DataFetcher\DataFetcherChoiceType;
use Sylius\Bundle\ReportBundle\Form\Type\Renderer\RendererChoiceType;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Report\DataFetcher\DataFetcherInterface;
use Sylius\Component\Report\Renderer\RendererInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 * @author Mateusz Zalewski <mateusz.zalewski@lakion.com>
 */
class ReportType extends AbstractResourceType
{
    /**
     * @var ServiceRegistryInterface
     */
    protected $rendererRegistry;

    /**
     * @var ServiceRegistryInterface
     */
    protected $dataFetcherRegistry;

    /**
     * @param ServiceRegistryInterface $rendererRegistry
     * @param ServiceRegistryInterface $dataFetcherRegistry
     */
    public function __construct(
        $dataClass,
        array $validationGroups,
        ServiceRegistryInterface $rendererRegistry,
        ServiceRegistryInterface $dataFetcherRegistry
    ) {
        parent::__construct($dataClass, $validationGroups);

        $this->rendererRegistry = $rendererRegistry;
        $this->dataFetcherRegistry = $dataFetcherRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->addEventSubscriber(new AddCodeFormSubscriber())
            ->addEventSubscriber(new BuildReportDataFetcherFormSubscriber($this->dataFetcherRegistry, $builder->getFormFactory()))
            ->addEventSubscriber(new BuildReportRendererFormSubscriber($this->rendererRegistry, $builder->getFormFactory()))
            ->add('name', TextType::class, [
                'label' => 'sylius.form.report.name',
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'sylius.form.report.description',
                'required' => false,
            ])
            ->add('dataFetcher', DataFetcherChoiceType::class, [
                'label' => 'sylius.form.report.data_fetcher',
            ])
            ->add('renderer', RendererChoiceType::class, [
                'label' => 'sylius.form.report.renderer.label',
            ])
        ;

        $prototypes = [
            'renderers' => [],
            'dataFetchers' => [],
        ];

        /** @var RendererInterface $renderer */
        foreach ($this->rendererRegistry->all() as $type => $renderer) {
            $formType = $renderer->getType();

            if (!$formType) {
                continue;
            }

            try {
                $prototypes['renderers'][$type] = $builder->create('rendererConfiguration', $formType)->getForm();
            } catch (\InvalidArgumentException $e) {
                continue;
            }
        }

        /** @var DataFetcherInterface $dataFetcher */
        foreach ($this->dataFetcherRegistry->all() as $type => $dataFetcher) {
            $formType = $dataFetcher->getType();

            if (!$formType) {
                continue;
            }

            try {
                $prototypes['dataFetchers'][$type] = $builder->create('dataFetcherConfiguration', $formType)->getForm();
            } catch (\InvalidArgumentException $e) {
                continue;
            }
        }

        $builder->setAttribute('prototypes', $prototypes);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['prototypes'] = [];

        foreach ($form->getConfig()->getAttribute('prototypes') as $group => $prototypes) {
            foreach ($prototypes as $type => $prototype) {
                $view->vars['prototypes'][$group][$group.'_'.$type] = $prototype->createView($view);
            }
        }
    }
}
