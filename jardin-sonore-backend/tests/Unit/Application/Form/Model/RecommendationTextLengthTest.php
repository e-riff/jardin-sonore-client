<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Form\Model;

use App\Application\Form\Model\MailingRecommendationFormModel;
use App\Application\Form\Model\NewsletterRecommendationFormModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class RecommendationTextLengthTest extends TestCase
{
    public function testRecommendationTextAllows420VisibleCharactersAndRejects421(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        foreach ([new MailingRecommendationFormModel(), new NewsletterRecommendationFormModel()] as $model) {
            $model->text = '<a href="https://example.test/a-long-url">' . str_repeat('é', 420) . '</a>';
            self::assertCount(0, $validator->validateProperty($model, 'text'));
            $model->text = str_repeat('&amp;', 419) . '<br>';
            self::assertCount(0, $validator->validateProperty($model, 'text'));
            $model->text = str_repeat('a', 421);
            self::assertCount(1, $validator->validateProperty($model, 'text'));
        }
    }

    public function testRecommendationRequiresVisibleNonWhitespaceText(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        foreach ([new MailingRecommendationFormModel(), new NewsletterRecommendationFormModel()] as $model) {
            $model->text = '<a href="https://example.test"> </a>';
            self::assertCount(1, $validator->validateProperty($model, 'text'));
        }
    }
}
