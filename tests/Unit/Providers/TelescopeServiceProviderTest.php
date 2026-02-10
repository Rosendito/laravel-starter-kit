<?php

declare(strict_types=1);

use App\Models\User;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\IncomingExceptionEntry;
use Laravel\Telescope\Telescope;

describe(TelescopeServiceProvider::class, function (): void {
    beforeEach(function (): void {
        Telescope::$filterUsing = [];
        Telescope::$hiddenRequestHeaders = [
            'authorization',
            'php-auth-pw',
        ];
        Telescope::$hiddenRequestParameters = [
            'password',
            'password_confirmation',
        ];
    });

    it('records everything in local environments', function (): void {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('environment')->with('local')->twice()->andReturn(true);

        $provider = new TelescopeServiceProvider($app);
        $provider->register();

        $filter = Telescope::$filterUsing[array_key_last(Telescope::$filterUsing)];

        expect($filter(IncomingEntry::make([])))->toBeTrue();
    });

    it('filters entries in non local environments by monitored conditions', function (): void {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('environment')->with('local')->twice()->andReturn(false);

        $entriesRepository = Mockery::mock(EntriesRepository::class);
        $entriesRepository->shouldReceive('isMonitoring')
            ->andReturnUsing(fn (array $tags): bool => in_array('monitored', $tags, true));
        app()->instance(EntriesRepository::class, $entriesRepository);

        $provider = new TelescopeServiceProvider($app);
        $provider->register();

        $filter = Telescope::$filterUsing[array_key_last(Telescope::$filterUsing)];

        $reportableException = new IncomingExceptionEntry(new Exception('reportable'), []);
        $failedRequest = IncomingEntry::make(['response_status' => 500])->type(EntryType::REQUEST);
        $failedJob = IncomingEntry::make(['status' => 'failed'])->type(EntryType::JOB);
        $scheduledTask = IncomingEntry::make([])->type(EntryType::SCHEDULED_TASK);
        $monitoredTag = IncomingEntry::make([])->tags(['monitored']);
        $ignoredEntry = IncomingEntry::make([]);

        expect($filter($reportableException))->toBeTrue()
            ->and($filter($failedRequest))->toBeTrue()
            ->and($filter($failedJob))->toBeTrue()
            ->and($filter($scheduledTask))->toBeTrue()
            ->and($filter($monitoredTag))->toBeTrue()
            ->and($filter($ignoredEntry))->toBeFalse()
            ->and(Telescope::$hiddenRequestParameters)->toContain('_token')
            ->and(Telescope::$hiddenRequestHeaders)->toContain('cookie', 'x-csrf-token', 'x-xsrf-token');
    });

    it('registers the telescope gate', function (): void {
        $app = Mockery::mock(Application::class);
        $provider = new TelescopeServiceProvider($app);

        (function (): void {
            $this->gate();
        })->call($provider);

        $user = User::factory()->make([
            'email' => 'nobody@example.test',
        ]);

        expect(Gate::has('viewTelescope'))->toBeTrue()
            ->and(Gate::forUser($user)->allows('viewTelescope'))->toBeFalse();
    });
});
