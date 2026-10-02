<?php

declare(strict_types=1);

namespace Raid\Pillar\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Raid\Pillar\Repositories\BaseRepository;
use Raid\Pillar\Repositories\BaseRepositoryCache;

final class CacheTest extends CharacterizationTest
{
    public function test_real_redis_reads_write_invalidation_and_controls(): void
    {
        $this->app->instance('env', 'cache-proof');
        config()->set('database.redis.client', 'phpredis');
        config()->set('database.redis.default', ['host' => 'redis', 'port' => 6379, 'database' => 2]);
        config()->set('cache.stores.redis', ['driver' => 'redis', 'connection' => 'default']);
        config()->set('cache.prefix', 'pillar-test-'.bin2hex(random_bytes(8)));
        config()->set('repository-cache', ['enabled' => true, 'driver' => 'redis', 'ttl' => 60]);
        Schema::create('examples', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('payload');
            $table->softDeletes();
            $table->timestamps();
        });
        $inner = new class(new CharacterizedModel) extends BaseRepository {};
        $repo = new BaseRepositoryCache($inner);
        $model = $repo->create(['name' => 'before', 'payload' => []]);
        $this->assertTrue($repo->shouldCache());
        $this->assertSame('before', $repo->find($model->id)->name);
        $inner->updateByIdOrFail($model->id, ['name' => 'external']);
        $this->assertSame('before', $repo->find($model->id)->name);
        $this->assertSame('external', $repo->withoutCache()->find($model->id)->name);
        $this->assertSame('before', $repo->find($model->id)->name);
        $repo->updateByIdOrFail($model->id, ['name' => 'after']);
        $this->assertSame('after', $repo->find($model->id)->name);
        $repo->withBuilder($inner->getBuilder()->where('name', 'not-visible'));
        $this->assertNull($repo->find($model->id));
        $repo->resetBuilder();
        $this->assertSame('after', $repo->find($model->id)->name);
        $scopedInner = new class(new CharacterizedModel) extends BaseRepository {};
        $scoped = (new BaseRepositoryCache($scopedInner))->withBuilder($scopedInner->getBuilder()->where('name', 'after'));
        $scoped->updateByIdOrFail($model->id, ['name' => 'scoped-update']);
        $this->assertSame('scoped-update', $repo->find($model->id)->name);
        $repo->deleteByIdOrFail($model->id);
        $this->assertNull($repo->find($model->id));
        $repo->clearCache();
    }
}
