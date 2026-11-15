<?php

declare(strict_types=1);

use App\Actions\GetOpenPullRequestsAction;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

\test('invalid repository identifiers fail before HTTP requests', function (string $repository): void {
    $mock = new MockHandler;
    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $result = (new GetOpenPullRequestsAction(client: $client))->handle($repository, 'token');
    \expect($result['success'])->toBeFalse()->and($result['error'])->toContain('owner/repo');
})->with(['owner', 'owner/repo/extra', 'https://github.com/owner/repo', 'owner/repo?state=closed', "owner/repo\n"]);

\test('invalid GitHub inventory fails closed instead of implying no open PRs', function (string $body): void {
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], $body)]))]);
    $result = (new GetOpenPullRequestsAction(client: $client))->handle('owner/repo', 'token');

    \expect($result['success'])->toBeFalse()->and($result['prs'])->toBe([]);
})->with(['malformed', '{}', 'null', '{"message":"unexpected"}', '[{}]', '[{"number":"42"}]', '[{"number":0}]', '[{"number":-1}]']);

\test('all GitHub pages must be valid before inventory is accepted', function (): void {
    $page = \array_map(static fn (int $number): array => ['number' => $number], \range(1, 100));
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, [], \json_encode($page, JSON_THROW_ON_ERROR)),
        new Response(200, [], '{"message":"unexpected"}'),
    ]))]);
    $result = (new GetOpenPullRequestsAction(client: $client))->handle('owner/repo', 'token');
    \expect($result['success'])->toBeFalse()->and($result['prs'])->toBe([]);
});

\test('valid empty and paginated GitHub inventories are accepted', function (): void {
    foreach ([['[]', []], ['[{"number":42}]', [42]]] as [$body, $expected]) {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], $body)]))]);
        $result = (new GetOpenPullRequestsAction(client: $client))->handle('owner/repo', 'token');
        \expect($result['success'])->toBeTrue()->and($result['prs'])->toBe($expected);
    }
    $numbers = \range(1, 101);
    $page = \array_map(static fn (int $number): array => ['number' => $number], $numbers);
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, [], \json_encode(\array_slice($page, 0, 100), JSON_THROW_ON_ERROR)),
        new Response(200, [], \json_encode(\array_slice($page, 100), JSON_THROW_ON_ERROR)),
    ]))]);
    $result = (new GetOpenPullRequestsAction(client: $client))->handle('owner/repo', 'token');
    \expect($result['success'])->toBeTrue()->and($result['prs'])->toBe($numbers);
});

\test('GitHub inventory request errors preserve failure status', function (?int $status): void {
    $failure = $status === null
        ? new RequestException('Connection interrupted', new Request('GET', '/repos/owner/repo/pulls'))
        : new Response($status);
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([$failure]))]);
    $result = (new GetOpenPullRequestsAction(client: $client))->handle('owner/repo', 'token');

    \expect($result['success'])->toBeFalse()
        ->and($result['prs'])->toBe([])
        ->and($result['error'])->toContain('status '.($status ?? 0));
})->with([401, 403, 429, 500, [null]]);

\test('a pull request is confirmed closed only when GitHub returns the exact request', function (int $status, string $body, bool $expected): void {
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response($status, [], $body)]))]);

    \expect((new GetOpenPullRequestsAction(client: $client))->isClosed('owner/repo', 'token', 42))->toBe($expected);
})->with([
    'closed' => [200, '{"number":42,"state":"closed"}', true],
    'open' => [200, '{"number":42,"state":"open"}', false],
    'different request' => [200, '{"number":43,"state":"closed"}', false],
    'malformed response' => [200, 'invalid json', false],
    'not found' => [404, '{}', false],
    'redirect' => [302, '{"number":42,"state":"closed"}', false],
]);

\test('invalid pull request references are rejected before requesting GitHub', function (string $repository, int $number): void {
    $mock = new MockHandler([new Response(200)]);
    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $action = new GetOpenPullRequestsAction(client: $client);

    \expect($action->isClosed($repository, 'token', $number))->toBeFalse()
        ->and($mock->count())->toBe(1);
})->with([['owner', 42], ['owner/repo', 0]]);
