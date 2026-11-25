<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Form\Model;

use App\Application\Form\Model\MailingRecommendationFormModel;
use App\Application\Form\Model\NewsletterRecommendationFormModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class RecommendationTextLengthTest extends TestCase
{
    public function testRecommendationTextAllows400CharactersAndRejects401(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        foreach ([new MailingRecommendationFormModel(), new NewsletterRecommendationFormModel()] as $model) {
            $model->text = str_repeat('a', 400);
            self::assertCount(0, $validator->validateProperty($model, 'text'));
            $model->text = str_repeat('a', 401);
            self::assertCount(1, $validator->validateProperty($model, 'text'));
        }
    }
}
