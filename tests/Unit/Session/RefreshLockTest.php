<?php

use IvanVasechko\Oukitel\Exceptions\CacheFailed;
use IvanVasechko\Oukitel\Session\InMemorySessionStore;
use IvanVasechko\Oukitel\Session\Psr16SessionStore;
use IvanVasechko\Oukitel\Tests\Support\FrozenClock;
use IvanVasechko\Oukitel\Tests\Support\RecordingCache;

it('excludes a second worker until the owner releases the PSR-16 lock', function () {
    $cache = new RecordingCache;
    $first = new Psr16SessionStore($cache);
    $second = new Psr16SessionStore($cache);
    $owner = $first->acquireRefreshLock('example-key', 30);

    expect($owner)->toMatch('/^[a-f0-9]{32}$/D');
    expect($cache->ttls['example-key'])->toBe(30);
    expect($second->acquireRefreshLock('example-key', 30))->toBeNull();
    $second->releaseRefreshLock('example-key', str_repeat('0', 32));
    expect($second->acquireRefreshLock('example-key', 30))->toBeNull();
    $first->releaseRefreshLock('example-key', $owner);
    expect($second->acquireRefreshLock('example-key', 30))->not->toBeNull();
});

it('expires memory locks at the exact boundary without letting an old owner release a replacement', function () {
    $clock = new FrozenClock;
    $store = new InMemorySessionStore($clock);
    $first = $store->acquireRefreshLock('example-key', 30);
    $clock->timestamp += 29;
    expect($store->acquireRefreshLock('example-key', 30))->toBeNull();
    $clock->timestamp++;
    $second = $store->acquireRefreshLock('example-key', 30);
    expect($second)->not->toBeNull()->not->toBe($first);
    $store->releaseRefreshLock('example-key', $first);
    expect($store->acquireRefreshLock('example-key', 30))->toBeNull();
    $store->releaseRefreshLock('example-key', $second);
    expect($store->acquireRefreshLock('example-key', 30))->not->toBeNull();
});

it('does not delete a replacement PSR-16 lock or claim a lock lost during acquisition', function () {
    $cache = new RecordingCache;
    $store = new Psr16SessionStore($cache);
    $first = $store->acquireRefreshLock('example-key', 30);
    $cache->values['example-key'] = str_repeat('a', 32);
    $store->releaseRefreshLock('example-key', $first);
    expect($cache->values['example-key'])->toBe(str_repeat('a', 32));
    $cache->values = [];
    $cache->afterSet = function (string $key) use ($cache): void {
        $cache->values[$key] = str_repeat('b', 32);
    };

    expect($store->acquireRefreshLock('example-key', 30))->toBeNull();
    expect($cache->values['example-key'])->toBe(str_repeat('b', 32));
});

it('fails closed on malformed locks and failed cache mutations', function (string $failure) {
    $cache = new RecordingCache;
    $store = new Psr16SessionStore($cache);
    if ($failure === 'malformed') {
        $cache->values['example-key'] = 'private';
    } else {
        $cache->failure = $failure;
        $cache->throw = $failure === 'get';
    }

    expect(fn () => $store->acquireRefreshLock('example-key', 30))->toThrow(CacheFailed::class);
})->with(['get', 'set', 'malformed']);

it('surfaces failed lock release rather than reporting success', function () {
    $cache = new RecordingCache;
    $store = new Psr16SessionStore($cache);
    $owner = $store->acquireRefreshLock('example-key', 30);
    $cache->failure = 'delete';

    expect(fn () => $store->releaseRefreshLock('example-key', $owner))->toThrow(CacheFailed::class);
});

it('rejects nonpositive lock TTLs in both stores', function () {
    foreach ([new InMemorySessionStore, new Psr16SessionStore(new RecordingCache)] as $store) {
        expect(fn () => $store->acquireRefreshLock('example-key', 0))->toThrow(InvalidArgumentException::class);
    }
});
