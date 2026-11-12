<?php

namespace Tests\Feature\Cms;

use App\Cms\Fields\ConditionalLogic;
use App\Cms\Fields\LocationMatcher;
use Tests\TestCase;

class ConditionalLogicTest extends TestCase
{
    private function rule(string $operator, string $value = ''): array
    {
        return ['conditional_logic' => [[['field' => 'x', 'operator' => $operator, 'value' => $value]]]];
    }

    public function test_operators(): void
    {
        $logic = new ConditionalLogic;

        $this->assertTrue($logic->passes($this->rule('==', 'a'), ['x' => ['a', 'b']]));
        $this->assertTrue($logic->passes($this->rule('!=', 'a'), ['x' => 'b']));
        $this->assertTrue($logic->passes($this->rule('==empty'), ['x' => '']));
        $this->assertFalse($logic->passes($this->rule('!=empty'), ['x' => null]));
        $this->assertTrue($logic->passes($this->rule('==pattern', '^ab'), ['x' => 'abc']));
        $this->assertTrue($logic->passes($this->rule('==contains', 'bc'), ['x' => 'abcd']));
    }

    public function test_or_between_groups_and_and_within_group(): void
    {
        $logic = new ConditionalLogic;
        $or = ['conditional_logic' => [
            [['field' => 'x', 'operator' => '==', 'value' => '1']],
            [['field' => 'y', 'operator' => '==', 'value' => '1']],
        ]];
        $and = ['conditional_logic' => [[
            ['field' => 'x', 'operator' => '==', 'value' => '1'],
            ['field' => 'y', 'operator' => '==', 'value' => '1'],
        ]]];

        $this->assertTrue($logic->passes($or, ['x' => '0', 'y' => '1']));
        $this->assertFalse($logic->passes($and, ['x' => '1', 'y' => '0']));
    }

    public function test_location_rules(): void
    {
        $matcher = new LocationMatcher;
        $rules = [
            [['param' => 'content_type', 'operator' => '==', 'value' => 'page'], ['param' => 'template', 'operator' => '!=', 'value' => 'pelna']],
            [['param' => 'term', 'operator' => '==', 'value' => '7']],
        ];

        $this->assertTrue($matcher->matches($rules, ['content_type' => 'page', 'template' => 'default']));
        $this->assertFalse($matcher->matches($rules, ['content_type' => 'page', 'template' => 'pelna']));
        $this->assertTrue($matcher->matches($rules, ['content_type' => 'post', 'term' => [3, 7]]));
        $this->assertFalse($matcher->matches([], ['content_type' => 'page']));
    }
}
