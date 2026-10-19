<?php

namespace Modules\Glitter\PolicyEngine\Providers;

use App\Extension\BaseModuleServiceProvider;
use Modules\Glitter\PolicyEngine\Contracts\PolicyEvaluatorInterface;
use Modules\Glitter\PolicyEngine\Repositories\Contracts\EvaluationRepositoryInterface;
use Modules\Glitter\PolicyEngine\Repositories\Contracts\PolicyRepositoryInterface;
use Modules\Glitter\PolicyEngine\Repositories\EvaluationRepository;
use Modules\Glitter\PolicyEngine\Repositories\PolicyRepository;
use Modules\Glitter\PolicyEngine\Rules\RuleDefinitionValidator;
use Modules\Glitter\PolicyEngine\Services\PolicyEvaluator;

final class PolicyEngineServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleIdentifier = 'glitter-policy_engine';

    protected array $repositories = [
        PolicyRepositoryInterface::class => PolicyRepository::class,
        EvaluationRepositoryInterface::class => EvaluationRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(RuleDefinitionValidator::class);
        $this->app->singleton(PolicyEvaluatorInterface::class, PolicyEvaluator::class);
    }
}
