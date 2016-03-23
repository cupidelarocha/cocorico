<?php

/*
 * This file is part of the Cocorico package.
 *
 * (c) Cocolabs SAS <contact@cocolabs.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cocorico\CoreBundle\Form\Type\Dashboard;

use Cocorico\CoreBundle\Form\Type\ListingDiscountType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ListingEditDiscountType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'discounts',
                'collection',
                array(
                    'allow_delete' => true,
                    'allow_add' => true,
                    'type' => new ListingDiscountType(),
                    'by_reference' => false,
                    'prototype' => true,
                    /** @Ignore */
                    'label' => false,
                    'cascade_validation' => true,//Important to have error on collection item field!
                )
            );
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        parent::setDefaultOptions($resolver);
        $resolver->setDefaults(
            array(
                'data_class' => 'Cocorico\CoreBundle\Entity\Listing',
                'translation_domain' => 'cocorico_listing',
                'cascade_validation' => true,//To have error on collection item field
            )
        );
    }

    public function getName()
    {
        return 'listing_edit_discounts';
    }

}
