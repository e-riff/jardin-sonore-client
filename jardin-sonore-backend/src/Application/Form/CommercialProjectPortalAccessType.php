<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Portal\OrganizationAccessEmailSelection;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<array{knownEmail?: ?OrganizationAccessEmailSelection, freeEmail?: ?string}> */
final class CommercialProjectPortalAccessType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $organizationEntity = $options['organization'];
        $emailChoices = [];
        foreach ([$organizationEntity, ...$organizationEntity->getPeople()->toArray()] as $directoryEntryEntity) {
            if (!$directoryEntryEntity->isActive()) {
                continue;
            }
            foreach ($directoryEntryEntity->getContactDetails()?->getEmailContactLinks() ?? [] as $emailContactLinkEntity) {
                $emailContactEntity = $emailContactLinkEntity->getEmailContact();
                if (!$emailContactLinkEntity->isActive() || !$emailContactEntity?->isActive()) {
                    continue;
                }
                $emailAddress = $emailContactEntity->getEmailAddress();
                $name = $directoryEntryEntity instanceof PersonEntity ? (string) $directoryEntryEntity : $organizationEntity->getName();
                $emailChoices["{$name} — {$emailAddress}"] = new OrganizationAccessEmailSelection($emailAddress, $directoryEntryEntity instanceof PersonEntity ? $directoryEntryEntity : null);
            }
        }
        $builder
            ->add('knownEmail', ChoiceType::class, [
                'label' => 'project.portal_access.known_email',
                'choices' => $emailChoices,
                'choice_translation_domain' => false,
                'choice_value' => static fn (?OrganizationAccessEmailSelection $selection): string => null === $selection ? '' : ($selection->personEntity?->getId() ?? 'organization') . ':' . $selection->emailAddress,
                'placeholder' => 'project.portal_access.choose_email',
                'required' => false,
            ])
            ->add('freeEmail', EmailType::class, [
                'label' => 'project.portal_access.free_email',
                'help' => 'project.portal_access.free_email_help',
                'required' => false,
                'constraints' => [new Assert\Email(), new Assert\Length(max: 180)],
                'attr' => ['maxlength' => 180, 'autocomplete' => 'off'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'commercial']);
        $resolver->setRequired('organization');
        $resolver->setAllowedTypes('organization', OrganizationEntity::class);
    }
}
