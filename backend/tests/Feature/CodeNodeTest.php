<?php

use App\Engine\Nodes\Core\CodeNode;
use App\Engine\Runners\NodePayload;
use App\Engine\WorkflowGraph;

beforeEach(function () {
    if (! extension_loaded('v8js')) {
        $this->markTestSkipped('V8Js extension is not installed');
    }
});

test('code node executes simple javascript', function () {
    $node = new CodeNode;

    $payload = new NodePayload(
        nodeId: 'code_1',
        config: [
            'code' => 'var output = { greeting: "Hello World" };',
        ],
        inputData: [],
        graph: new WorkflowGraph([]),
    );

    $result = $node->handle($payload);

    expect($result->isCompleted())->toBeTrue()
        ->and($result->output)->toHaveKey('greeting')
        ->and($result->output['greeting'])->toBe('Hello World');
});

test('code node can access input data', function () {
    $node = new CodeNode;

    $payload = new NodePayload(
        nodeId: 'code_1',
        config: [
            'code' => 'var output = { name: input.user.name.toUpperCase(), age: input.user.age + 1 };',
        ],
        inputData: [
            'user' => [
                'name' => 'john',
                'age' => 30,
            ],
        ],
        graph: new WorkflowGraph([]),
    );

    $result = $node->handle($payload);

    expect($result->isCompleted())->toBeTrue()
        ->and($result->output['name'])->toBe('JOHN')
        ->and($result->output['age'])->toBe(31);
});

test('code node can perform array operations', function () {
    $node = new CodeNode;

    $payload = new NodePayload(
        nodeId: 'code_1',
        config: [
            'code' => <<<'JS'
var numbers = input.numbers;
var output = {
    sum: numbers.reduce((a, b) => a + b, 0),
    doubled: numbers.map(n => n * 2),
    filtered: numbers.filter(n => n > 5)
};
JS,
        ],
        inputData: [
            'numbers' => [1, 3, 5, 7, 9],
        ],
        graph: new WorkflowGraph([]),
    );

    $result = $node->handle($payload);

    expect($result->isCompleted())->toBeTrue()
        ->and($result->output['sum'])->toBe(25)
        ->and($result->output['doubled'])->toBe([2, 6, 10, 14, 18])
        ->and($result->output['filtered'])->toBe([7, 9]);
});

test('code node handles syntax errors', function () {
    $node = new CodeNode;

    $payload = new NodePayload(
        nodeId: 'code_1',
        config: [
            'code' => 'var output = { invalid syntax here',
        ],
        inputData: [],
        graph: new WorkflowGraph([]),
    );

    $result = $node->handle($payload);

    expect($result->isFailed())->toBeTrue()
        ->and($result->error['code'])->toBe('EXECUTION_ERROR');
});

test('code node respects time limit', function () {
    $node = new CodeNode;

    $payload = new NodePayload(
        nodeId: 'code_1',
        config: [
            'code' => 'while(true) {}', // Infinite loop
            'timeout' => 1, // 1 second
        ],
        inputData: [],
        graph: new WorkflowGraph([]),
    );

    $result = $node->handle($payload);

    expect($result->isFailed())->toBeTrue()
        ->and($result->error['code'])->toBe('TIMEOUT');
})->timeout(5);

test('code node returns input when no output is set', function () {
    $node = new CodeNode;

    $payload = new NodePayload(
        nodeId: 'code_1',
        config: [
            'code' => '// No output variable set',
        ],
        inputData: ['test' => 'data'],
        graph: new WorkflowGraph([]),
    );

    $result = $node->handle($payload);

    expect($result->isCompleted())->toBeTrue()
        ->and($result->output)->toBe(['test' => 'data']);
});

test('code node can work with json data', function () {
    $node = new CodeNode;

    $payload = new NodePayload(
        nodeId: 'code_1',
        config: [
            'code' => <<<'JS'
var users = input.users;
var output = {
    count: users.length,
    names: users.map(u => u.name),
    adults: users.filter(u => u.age >= 18).length
};
JS,
        ],
        inputData: [
            'users' => [
                ['name' => 'Alice', 'age' => 25],
                ['name' => 'Bob', 'age' => 17],
                ['name' => 'Charlie', 'age' => 30],
            ],
        ],
        graph: new WorkflowGraph([]),
    );

    $result = $node->handle($payload);

    expect($result->isCompleted())->toBeTrue()
        ->and($result->output['count'])->toBe(3)
        ->and($result->output['names'])->toBe(['Alice', 'Bob', 'Charlie'])
        ->and($result->output['adults'])->toBe(2);
});

test('code node fails when no code is provided', function () {
    $node = new CodeNode;

    $payload = new NodePayload(
        nodeId: 'code_1',
        config: [],
        inputData: [],
        graph: new WorkflowGraph([]),
    );

    $result = $node->handle($payload);

    expect($result->isFailed())->toBeTrue()
        ->and($result->error['code'])->toBe('EMPTY_CODE');
});
