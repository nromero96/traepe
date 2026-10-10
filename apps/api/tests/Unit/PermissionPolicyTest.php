<?php

namespace Tests\Unit;

use App\Modules\Identity\Application\Authorization\AuthorizationDirectory;
use App\Modules\Identity\Application\Authorization\PermissionService;
use App\Modules\Identity\Application\Authorization\ResourceContextResolver;
use App\Modules\Identity\Domain\Authorization\Decision;
use App\Modules\Identity\Domain\Authorization\PermissionEffect;
use App\Modules\Identity\Domain\Authorization\PermissionPolicy;
use App\Modules\Identity\Domain\Authorization\PermissionRule;
use App\Modules\Identity\Domain\Authorization\ResourceContext;
use App\Modules\Identity\Domain\Authorization\ResourceReference;
use App\Modules\Identity\Domain\Authorization\Scope;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PermissionPolicyTest extends TestCase
{
    private const ACTOR = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private const OTHER = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    private function scope(string $type = 'branch', string $id = self::ACTOR): Scope
    {
        return new Scope($type, $id);
    }

    private function resource(string $id = self::ACTOR): ResourceReference
    {
        return new ResourceReference('local.fixture', $id);
    }

    /** @param iterable<PermissionRule> $rules */
    private function decide(iterable $rules, ?ResourceContext $context = null, bool $active = true): Decision
    {
        return (new PermissionPolicy)->evaluate(self::ACTOR, $active, 'identity.local.probe', $this->scope(), $this->resource(), $context ?? new ResourceContext($this->resource(), $this->scope()), $rules, new DateTimeImmutable('2030-01-01T00:00:00Z'));
    }

    public function test_default_denial_and_exact_scoped_role_or_grant_allow(): void
    {
        $this->assertSame(Decision::NoGrant, $this->decide([]));
        $rule = new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope(), PermissionEffect::Allow);
        $decision = $this->decide([$rule]);
        $this->assertSame(Decision::Allowed, $decision);
        $this->assertTrue($decision->allowed());
        $this->assertFalse(Decision::NoGrant->allowed());
    }

    public function test_deny_precedence_is_independent_of_rule_order(): void
    {
        $allow = new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope(), PermissionEffect::Allow);
        $deny = new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope(), PermissionEffect::Deny);
        $this->assertSame(Decision::ExplicitDeny, $this->decide([$allow, $deny]));
        $this->assertSame(Decision::ExplicitDeny, $this->decide([$deny, $allow]));
    }

    public function test_actor_capability_and_scope_must_all_match(): void
    {
        $rules = [
            new PermissionRule(self::OTHER, 'identity.local.probe', $this->scope(), PermissionEffect::Allow),
            new PermissionRule(self::ACTOR, 'another.fixture', $this->scope(), PermissionEffect::Allow),
            new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope('branch', self::OTHER), PermissionEffect::Allow),
            new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope('merchant'), PermissionEffect::Allow),
            new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope('platform'), PermissionEffect::Allow),
        ];
        foreach ($rules as $rule) {
            $this->assertSame(Decision::NoGrant, $this->decide([$rule]));
        }
    }

    public function test_other_actor_deny_does_not_override_a_valid_allow(): void
    {
        $this->assertSame(Decision::Allowed, $this->decide([
            new PermissionRule(self::OTHER, 'identity.local.probe', $this->scope(), PermissionEffect::Deny),
            new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope(), PermissionEffect::Allow),
        ]));
    }

    public function test_expiry_boundary_and_timezone_represent_the_same_instant(): void
    {
        foreach (['2029-12-31T23:59:59Z', '2030-01-01T00:00:00Z', '2029-12-31T19:00:00-05:00'] as $expiry) {
            $this->assertSame(Decision::NoGrant, $this->decide([
                new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope(), PermissionEffect::Allow, new DateTimeImmutable($expiry)),
            ]));
        }
        $allow = new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope(), PermissionEffect::Allow, new DateTimeImmutable('2030-01-01T00:00:01Z'));
        $expiredDeny = new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope(), PermissionEffect::Deny, new DateTimeImmutable('2030-01-01T00:00:00Z'));
        $this->assertSame(Decision::Allowed, $this->decide([$expiredDeny, $allow]));
    }

    public function test_blocked_actor_cannot_use_a_valid_grant(): void
    {
        $this->assertSame(Decision::InactiveActor, $this->decide([
            new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope(), PermissionEffect::Allow),
        ], active: false));
    }

    public function test_resolved_resource_identity_and_scope_are_checked(): void
    {
        $allow = new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope(), PermissionEffect::Allow);
        $this->assertSame(Decision::UnknownResource, $this->decide([$allow], new ResourceContext($this->resource(self::OTHER), $this->scope())));
        $this->assertSame(Decision::ScopeMismatch, $this->decide([$allow], new ResourceContext($this->resource(), $this->scope('branch', self::OTHER))));
        $this->assertSame(Decision::UnknownResource, $this->decide([$allow], new ResourceContext(new ResourceReference('another.fixture', self::ACTOR), $this->scope())));
    }

    public function test_service_resolves_context_on_server_and_denies_unknown_resource(): void
    {
        $directory = $this->createMock(AuthorizationDirectory::class);
        $directory->method('isActive')->with(self::ACTOR)->willReturn(true);
        $directory->method('rules')->with(self::ACTOR)->willReturn([
            new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope(), PermissionEffect::Allow),
        ]);
        $resolver = $this->createMock(ResourceContextResolver::class);
        $resolver->expects($this->once())->method('resolve')->with($this->resource())->willReturn(null);
        $this->assertSame(Decision::UnknownResource, (new PermissionService($directory, $resolver))->decide(self::ACTOR, 'identity.local.probe', $this->scope(), $this->resource(), new DateTimeImmutable('2030-01-01T00:00:00Z')));
    }

    public function test_inactive_actor_does_not_load_resources_or_rules(): void
    {
        $directory = $this->createMock(AuthorizationDirectory::class);
        $directory->method('isActive')->willReturn(false);
        $directory->expects($this->never())->method('rules');
        $resolver = $this->createMock(ResourceContextResolver::class);
        $resolver->expects($this->never())->method('resolve');
        $this->assertSame(Decision::InactiveActor, (new PermissionService($directory, $resolver))->decide(self::ACTOR, 'identity.local.probe', $this->scope(), $this->resource(), new DateTimeImmutable('2030-01-01T00:00:00Z')));
    }

    public function test_wildcard_grants_are_not_valid_rules(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PermissionRule(self::ACTOR, '*', $this->scope(), PermissionEffect::Allow);
    }

    public function test_sequential_evaluations_do_not_retain_previous_grants(): void
    {
        $policy = new PermissionPolicy;
        $rule = new PermissionRule(self::ACTOR, 'identity.local.probe', $this->scope(), PermissionEffect::Allow);
        foreach ([[$rule], []] as $index => $rules) {
            $decision = $policy->evaluate(self::ACTOR, true, 'identity.local.probe', $this->scope(), $this->resource(), new ResourceContext($this->resource(), $this->scope()), $rules, new DateTimeImmutable('2030-01-01T00:00:00Z'));
            $this->assertSame($index === 0 ? Decision::Allowed : Decision::NoGrant, $decision);
        }
    }
}
