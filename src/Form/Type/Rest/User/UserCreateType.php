<?php
declare(strict_types=1);
/**
 * /src/Form/Type\Rest/User/UserCreateType.php
 *
 * @author  TLe, Tarmo Leppänen <tarmo.leppanen@protacon.com>
 */
namespace App\Form\Type\Rest\User;

use App\Rest\DTO\User as UserDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class UserCreateType
 *
 * @package App\Form\Type\Rest\User
 * @author  TLe, Tarmo Leppänen <tarmo.leppanen@protacon.com>
 */
class UserCreateType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     *
     * @throws \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'username',
                Type\TextType::class,
                [
                    'label'         => 'Username',
                    'empty_data'    => '',
                ]
            )
            ->add(
                'firstname',
                Type\TextType::class,
                [
                    'label'         => 'Firstname',
                    'empty_data'    => '',
                ]
            )
            ->add(
                'surname',
                Type\TextType::class,
                [
                    'label'         => 'Surname',
                    'empty_data'    => '',
                ]
            )
            ->add(
                'email',
                Type\EmailType::class,
                [
                    'label'         => 'Email address',
                    'empty_data'    => '',
                ]
            )
            ->add(
                'password',
                Type\TextType::class,
                [
                    'label'         => 'Password',
                    'empty_data'    => '',
                ]
            );
    }

    /**
     * Configures the options for this type.
     *
     * @param OptionsResolver $resolver The resolver for the options
     *
     * @throws \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'        => UserDto::class,
            'validation_groups' => ['Create', 'Default'],
        ]);
    }
}