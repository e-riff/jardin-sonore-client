<?php

declare(strict_types=1);

namespace App\Application\Form\ChoiceLoader;

use App\Application\Mailing\NewsletterAudienceOptionsProviderInterface;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

final readonly class MunicipalityInseeCodeChoiceLoader implements ChoiceLoaderInterface
{
    public function __construct(
        private NewsletterAudienceOptionsProviderInterface $newsletterAudienceOptionsProvider,
    ) {
    }

    public function loadChoiceList(?callable $value = null): ChoiceListInterface
    {
        return new ArrayChoiceList([], $value);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    public function loadChoicesForValues(array $values, ?callable $value = null): array
    {
        return $this->newsletterAudienceOptionsProvider->getExistingMunicipalityInseeCodes($values);
    }

    /**
     * @param list<string> $choices
     *
     * @return list<string>
     */
    public function loadValuesForChoices(array $choices, ?callable $value = null): array
    {
        return $this->newsletterAudienceOptionsProvider->getExistingMunicipalityInseeCodes($choices);
    }
}
