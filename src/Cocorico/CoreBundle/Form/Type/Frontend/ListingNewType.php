<?php

/*
 * This file is part of the Cocorico package.
 *
 * (c) Cocolabs SAS <contact@cocolabs.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cocorico\CoreBundle\Form\Type\Frontend;

use Cocorico\CoreBundle\Entity\Listing;
use Cocorico\CoreBundle\Form\Type\ImageType;
use Cocorico\UserBundle\Entity\User;
use Cocorico\UserBundle\Security\LoginManager;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Translation\TranslationContainerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Validator\Constraints\True;

class ListingNewType extends AbstractType implements TranslationContainerInterface
{
    public static $tacError = 'listing.form.tac.error';
    public static $credentialError = 'user.form.credential.error';
    public static $emptyTitle = 'listing.translation.title.default';

    private $securityContext;
    private $loginManager;
    private $request;
    private $locale;
    private $locales;

    /**
     * @param SecurityContext $securityContext
     * @param LoginManager    $loginManager
     * @param RequestStack    $requestStack
     * @param array           $locales
     */
    public function __construct(
        SecurityContext $securityContext,
        LoginManager $loginManager,
        RequestStack $requestStack,
        $locales
    ) {
        $this->securityContext = $securityContext;
        $this->loginManager = $loginManager;
        $this->request = $requestStack->getCurrentRequest();
        $this->locale = $this->request->getLocale();
        $this->locales = $locales;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        //Translations fields
        $titles = $descriptions = array();
        foreach ($this->locales as $i => $locale) {
            $titles[$locale] = array(
                'label' => 'listing.form.title',
//                'data' => self::$emptyTitle
            );
            $descriptions[$locale] = array(
                'label' => 'listing.form.description'
            );

            /*$rules[$locale] = array(
                'label' => 'listing.form.rules'
            );*/
        }

        $builder->add(
            'translations',
            'a2lix_translations',
            array(
                'required_locales' => array($this->locale),
                'fields' => array(
                    'title' => array(
                        'field_type' => 'text',
                        'locale_options' => $titles,
                    ),
                    'description' => array(
                        'field_type' => 'textarea',
                        'locale_options' => $descriptions
                    ),
                    'rules' => array(
                        /*'field_type' => 'textarea',
                        'locale_options' => $rules,*/
                        'display' => false
                    ),
                    'slug' => array(
                        'field_type' => 'hidden'
                    )
                ),
                /** @Ignore */
                'label' => false
            )
        );

        $builder
            ->add(
                'price',
                'price',
                array(
                    'label' => 'listing.form.price',
                )
            )
            ->add(
                'categories',
                'listing_category',
                array(
                    'block_name' => 'categories'
                )
            )
            ->add(
                'image',
                new ImageType()
            )
            ->add(
                'location',
                new ListingLocationType(),
                array(
                    'data_class' => 'Cocorico\CoreBundle\Entity\ListingLocation',
                    /** @Ignore */
                    'label' => false,
                )
            )
            ->add(
                "tac",
                "checkbox",
                array(
                    'label' => 'listing.form.tac',
                    'mapped' => false,
                    'constraints' => new True(
                        array(
                            "message" => self::$tacError
                        )
                    ),
                )
            );

        /**
         * Set the user fields according to his logging status
         *
         * @param FormInterface $form
         */
        $formUserModifier = function (FormInterface $form) {
            //Not logged
            if (!$this->securityContext->isGranted('IS_AUTHENTICATED_FULLY')) {
                $form
                    ->add(//Login form
                        'user_login',
                        'user_login',
                        array(
                            'mapped' => false,
                            /** @Ignore */
                            'label' => false
                        )
                    )->add(//Registration form
                        'user',
                        'user_registration',
                        array(
                            /** @Ignore */
                            'label' => false
                        )
                    );
            } else {//Logged

                $form->add(
                    'user',
                    'entity_hidden',
                    array(
                        'data' => $this->securityContext->getToken()->getUser(),
                        'class' => 'Cocorico\UserBundle\Entity\User',
                        'data_class' => null
                    )
                );
            }
        };

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formUserModifier) {
                $formUserModifier($event->getForm());
            }
        );

        /**
         * Login user management
         *
         * @param FormInterface $form
         */
        $formUserLoginModifier = function (FormInterface $form) {
            if ($form->has('user_login')) {
                $userLoginData = $form->get('user_login')->getData();
                $username = $userLoginData["_username"];
                $password = $userLoginData["_password"];

                if ($username || $password) {
                    /** @var $user User */
                    $user = $this->loginManager->loginUser($username, $password);
                    if ($user) {
                        $form->getData()->setUser($user);
                        //Remove user registration form
                        //Remove user registration and login form and add user field
                        $form->remove("user");
                        $form->remove("user_login");
                        $form->add(
                            'user',
                            'entity_hidden',
                            array(
                                'data' => $this->securityContext->getToken()->getUser(),
                                'class' => 'Cocorico\UserBundle\Entity\User',
                                'data_class' => null
                            )
                        );

                    } else {
                        $form['user_login']['_username']->addError(
                            new FormError(self::$credentialError)
                        );
                        //TODO: Disable form register errors when try to login with error
                    }

                }
            }
        };

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event) use ($formUserLoginModifier) {
                $formUserLoginModifier($event->getForm());
            }
        );

    }


    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'Cocorico\CoreBundle\Entity\Listing',
                'intention' => 'listing_new',
                'translation_domain' => 'cocorico_listing',
                'cascade_validation' => true,
                //'validation_groups' => array('Listing'),
            )
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'listing_new';
    }

    /**
     * JMS Translation messages
     *
     * @return array
     */
    public static function getTranslationMessages()
    {
        $messages = array();
        $messages[] = new Message(self::$tacError, 'cocorico');
        $messages[] = new Message(self::$credentialError, 'cocorico');
        $messages[] = new Message(self::$emptyTitle, 'cocorico_listing');

        return $messages;
    }
}
