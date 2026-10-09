<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, string|null>> */
final class CommercialRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('organization', OrganizationAutocompleteType::class, [
                'label' => 'request.organization',
                'placeholder' => 'request.select_organization',
                'attr' => [
                    'data-commercial-request-contacts-target' => 'organization',
                    'data-action' => 'change->commercial-request-contacts#organizationChanged',
                ],
            ])
            ->add('newOrganizationName', TextType::class, ['label' => 'request.new_organization', 'required' => false])
            ->add('person', ChoiceType::class, [
                'label' => 'request.person',
                'required' => false,
                'placeholder' => 'request.select_person',
                'choices' => $options['people'],
                'choice_label' => static fn (PersonEntity $personEntity): string => (string) $personEntity . (null === $personEntity->getRole() ? '' : ' — ' . $personEntity->getRole()),
                'choice_value' => static fn (?PersonEntity $personEntity): string => (string) ($personEntity?->getId() ?? ''),
                'attr' => ['data-commercial-request-contacts-target' => 'person'],
            ])
            ->add('personMode', ChoiceType::class, [
                'label' => 'request.person_mode',
                'choices' => [
                    'request.person_mode_existing' => 'existing',
                    'request.person_mode_new' => 'new',
                    'request.person_mode_unknown' => 'unknown',
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'existing',
                'choice_attr' => static fn (): array => ['data-commercial-request-contacts-target' => 'personMode'],
            ])
            ->add('newPersonFirstName', TextType::class, ['label' => 'request.new_person_first_name', 'required' => false])
            ->add('newPersonLastName', TextType::class, ['label' => 'request.new_person_last_name', 'required' => false])
            ->add('newPersonRole', TextType::class, ['label' => 'request.new_person_role', 'required' => false])
            ->add('emailAddress', EmailType::class, ['label' => 'request.email', 'required' => false])
            ->add('phone', TextType::class, ['label' => 'request.phone', 'required' => false])
            ->add('message', TextareaType::class, ['label' => 'request.message'])
            ->add('submit', SubmitType::class, ['label' => 'request.save', 'attr' => ['class' => 'internal-button']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'commercial', 'people' => []]);
        $resolver->setAllowedTypes('people', 'array');
    }
}
