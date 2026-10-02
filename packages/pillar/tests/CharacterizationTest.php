<?php

declare(strict_types=1);

namespace Raid\Pillar\Tests;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Raid\Pillar\Actions\BaseAction;
use Raid\Pillar\Repositories\BaseRepository;
use Raid\Pillar\Repositories\ValueObjects\QueryOptions;

class CharacterizedModel extends Model
{
    use SoftDeletes;

    protected $table = 'examples';

    protected $guarded = [];

    protected $casts = ['payload' => 'array'];
}

class CharacterizedDependency
{
    public function value(): string
    {
        return 'bound';
    }
}

class CharacterizedAction extends BaseAction
{
    public function __construct(private readonly CharacterizedDependency $dependency) {}

    public function handle(bool $fail = false): string
    {
        if ($fail) {
            throw new DomainException('expected');
        }

        return $this->dependency->value();
    }
}

class CharacterizationTest extends TestCase
{
    public function test_transaction_uses_model_connection_even_when_default_differs(): void
    {
        config()->set('database.connections.alternate', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        Schema::connection('alternate')->create('examples', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('payload');
            $table->softDeletes();
            $table->timestamps();
        });
        $model = (new CharacterizedModel)->setConnection('alternate');
        $repo = new class($model) extends BaseRepository {};
        try {
            $repo->transaction(function () use ($repo): void {
                $repo->create(['name' => 'rollback', 'payload' => []]);
                throw new DomainException;
            });
        } catch (DomainException) {
        }
        $this->assertFalse($repo->exists(['name' => 'rollback']));
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'test');
        $app['config']->set('database.connections.test', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    public function test_action_container_execution_and_domain_errors(): void
    {
        $this->app->instance(CharacterizedDependency::class, new CharacterizedDependency);
        $this->assertSame('bound', CharacterizedAction::exec());
        $this->assertSame('bound', $this->app->make(CharacterizedAction::class)->handle());
        $this->expectException(DomainException::class);
        CharacterizedAction::exec(true);
    }

    public function test_repository_lifecycle_isolation_options_and_connection_rollback(): void
    {
        Schema::create('examples', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('payload');
            $table->softDeletes();
            $table->timestamps();
        });
        $repo = new class(new CharacterizedModel) extends BaseRepository {};
        $first = $repo->create(['name' => 'a', 'payload' => ['n' => 1]]);
        $repo->create(['name' => 'b', 'payload' => []]);
        $events = 0;
        CharacterizedModel::updated(function () use (&$events): void {
            $events++;
        });
        $repo->updateByIdOrFail($first->id, ['payload' => ['n' => 2]]);
        $this->assertSame(1, $events);
        $this->assertSame(['n' => 2], $repo->find($first->id)->payload);
        $repo->update(['id' => $first->id], ['name' => 'bulk']);
        $this->assertSame(1, $events);
        $this->assertCount(1, $repo->getBy(['name' => 'b']));
        $this->assertCount(2, $repo->get());
        $this->assertSame('bulk', $repo->get(options: new QueryOptions(sort: 'id', direction: 'asc', limit: 1))->first()->name);
        try {
            $repo->transaction(function () use ($repo): void {
                $repo->create(['name' => 'rollback', 'payload' => []]);
                throw new DomainException;
            });
        } catch (DomainException) {
        }
        $this->assertFalse($repo->exists(['name' => 'rollback']));
        $repo->deleteByIdOrFail($first->id);
        $this->assertNull($repo->find($first->id));
        $this->assertCount(2, $repo->get(options: new QueryOptions(withTrashed: true)));
    }
}
