<?php

namespace Tests\Unit;

use App\Modules\Marketplace\Application\Commerce\LocalCommercialContextSource;
use App\Modules\Marketplace\Application\Commerce\ProbeLocalCommercialContext;
use App\Modules\Marketplace\Domain\Commerce\BranchPublicId;
use App\Modules\Marketplace\Domain\Commerce\CommercialContext;
use App\Modules\Marketplace\Domain\Commerce\MerchantPublicId;
use App\Modules\Marketplace\Domain\Coverage\MarketPublicId;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CommercialContextTest extends TestCase
{
    private const REFERENCE = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    private function context(): CommercialContext
    {
        return new CommercialContext(new MerchantPublicId(self::REFERENCE), new MarketPublicId(self::REFERENCE), new BranchPublicId(self::REFERENCE));
    }

    public function test_public_references_preserve_valid_ulids_including_bounds(): void
    {
        foreach ([MerchantPublicId::class, MarketPublicId::class, BranchPublicId::class] as $type) {
            foreach ([self::REFERENCE, str_repeat('0', 26), '7'.str_repeat('Z', 25)] as $value) {
                $this->assertSame($value, (new $type($value))->value);
            }
        }
    }

    public function test_references_reject_invalid_input_with_fixed_private_errors(): void
    {
        foreach ([MerchantPublicId::class => 'merchant', MarketPublicId::class => 'market', BranchPublicId::class => 'branch'] as $type => $label) {
            foreach (['', '1', strtolower(self::REFERENCE), '8'.substr(self::REFERENCE, 1), str_repeat('0', 25), str_repeat('0', 27), ' '.self::REFERENCE, self::REFERENCE.' ', self::REFERENCE."\n", self::REFERENCE."\0", substr(self::REFERENCE, 0, 25).'I', substr(self::REFERENCE, 0, 25).'O', substr(self::REFERENCE, 0, 25).'U'] as $value) {
                try {
                    new $type($value);
                    $this->fail('Invalid public reference accepted.');
                } catch (InvalidArgumentException $failure) {
                    $this->assertSame('Invalid public '.$label.' reference.', $failure->getMessage());
                }
            }
        }
    }

    public function test_context_and_nested_references_cannot_be_mutated(): void
    {
        $context = $this->context();
        foreach (['merchant', 'market', 'branch'] as $property) {
            $original = $context->$property;
            try {
                $context->$property = $original;
                $this->fail('Context mutation accepted.');
            } catch (Error) {
                $this->assertSame($original, $context->$property);
            }
            try {
                $original->value = str_repeat('0', 26);
                $this->fail('Reference mutation accepted.');
            } catch (Error) {
                $this->assertSame(self::REFERENCE, $original->value);
            }
        }
    }

    public function test_application_returns_only_the_source_match_with_exact_context(): void
    {
        $context = $this->context();
        foreach ([true, false] as $matched) {
            $source = $this->createMock(LocalCommercialContextSource::class);
            $source->expects($this->once())->method('matches')->with($this->identicalTo($context))->willReturn($matched);
            $this->assertSame($matched, (new ProbeLocalCommercialContext($source))->evaluate($context));
        }
        $this->assertSame('local-commercial-context-v1', ProbeLocalCommercialContext::VERSION);
    }
}
