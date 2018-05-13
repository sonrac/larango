<?php

namespace sonrac\Arango\tests;

use Illuminate\Support\Facades\DB;

class QueryBuilderTest extends BaseTestCase
{
    /**
     * @group QueryBuilderTest
     */
    public function tearDown()
    {
        DB::table('users')->truncate();
        DB::table('items')->truncate();
    }

    /**
     * @group QueryBuilderTest
     */
    public function testCollection()
    {
        $this->assertInstanceOf('sonrac\Arango\Query\QueryBuilder', DB::table('users'));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testGet()
    {
        $users = DB::table('users')->get();
        $this->assertEquals(0, count($users));

        DB::table('users')->insert(['name' => 'John Doe']);

        $users = DB::table('users')->get();
        $this->assertEquals(1, count($users));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testNoDocument()
    {
        $items = DB::table('items')->where('name', 'nothing')->get()->toArray();
        $this->assertEquals([], $items);

        $item = DB::table('items')->where('name', 'nothing')->first();
        $this->assertEquals(null, $item);

        $item = DB::table('items')->where('_id', '51c33d8981fec6813e00000a')->first();
        $this->assertEquals(null, $item);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testInsert()
    {
        DB::table('users')->insert([
            'tags' => 'tag1',
            'name' => 'John Doe',
        ]);

        $users = DB::table('users')->get();
        $this->assertEquals(1, count($users));

        $user = $users[0];
        $this->assertEquals('John Doe', $user['name']);
        $this->assertTrue(is_string($user['tags']));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testInsertGetId()
    {
        $id = DB::table('users')->insertGetId(['name' => 'John Doe']);
        $this->assertTrue(is_string($id));

    }

    /**
     * @group QueryBuilderTest
     */
    public function testBatchInsert()
    {
        DB::table('users')->insert([
            [
                'tags' => 'tag1',
                'name' => 'Jane Doe',
            ],
            [
                'tags' => 'tag3',
                'name' => 'John Doe',
            ],
        ]);

        $users = DB::table('users')->get();

        $this->assertEquals(2, count($users));
        $this->assertTrue(is_string($users[0]['tags']));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testCount()
    {
        DB::table('users')->insert(['name' => 'Jane Doe']);
        DB::table('users')->insert(['name' => 'Jane Doe']);

        $this->assertEquals(2, DB::table('users')->count());
    }

    /**
     * @group QueryBuilderTest
     */
    public function testUpdate()
    {
        DB::table('users')->insert(['name' => 'John Doe', 'age' => 30]);
        DB::table('users')->insert(['name' => 'Jane Doe', 'age' => 20]);

        DB::table('users')->where('name', 'John Doe')->update(['age' => 100]);

        $john = DB::table('users')->where('name', 'John Doe')->first();
        $jane = DB::table('users')->where('name', 'Jane Doe')->first();
        $this->assertEquals(100, $john['age']);
        $this->assertEquals(20, $jane['age']);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testDelete()
    {
        DB::table('users')->insert(['name' => 'John Doe', 'age' => 25]);
        DB::table('users')->insert(['name' => 'Jane Doe', 'age' => 20]);

        DB::table('users')->where('age', '<', 10)->delete();
        $this->assertEquals(2, DB::table('users')->count());

        DB::table('users')->where('age', '<', 25)->delete();
        $this->assertEquals(1, DB::table('users')->count());
    }

    /**
     * @group QueryBuilderTest
     */
    public function testTruncate()
    {
        DB::table('users')->insert(['name' => 'John Doe']);
        DB::table('users')->truncate();
        $this->assertEquals(0, DB::table('users')->count());
    }

    /**
     * @group QueryBuilderTest
     */
    public function testTake()
    {
        DB::table('items')->insert(['name' => 'knife', 'type' => 'sharp', 'amount' => 34]);
        DB::table('items')->insert(['name' => 'fork',  'type' => 'sharp', 'amount' => 20]);
        DB::table('items')->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 3]);
        DB::table('items')->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 14]);

        $items = DB::table('items')->orderBy('name')->take(2)->get();
        $this->assertEquals(2, count($items));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testSkip()
    {
        DB::table('items')->insert(['name' => 'knife', 'type' => 'sharp', 'amount' => 34]);
        DB::table('items')->insert(['name' => 'fork',  'type' => 'sharp', 'amount' => 20]);
        DB::table('items')->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 3]);
        DB::table('items')->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 14]);

        $items = DB::table('items')->orderBy('name')->skip(2)->limit(3)->get();
        $this->assertEquals(2, count($items));
    }

    /**
     * @group QueryBuilderTest
     */
    public function testPluck()
    {
        DB::table('users')->insert(['name' => 'Jane Doe', 'age' => 20]);
        DB::table('users')->insert(['name' => 'John Doe', 'age' => 25]);

        $age = DB::table('users')->where('name', 'John Doe')->pluck('age')->toArray();
        $this->assertEquals([25], $age);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testList()
    {
        DB::table('items')->insert(['name' => 'knife', 'type' => 'sharp', 'amount' => 34]);
        DB::table('items')->insert(['name' => 'fork',  'type' => 'sharp', 'amount' => 20]);
        DB::table('items')->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 3]);
        DB::table('items')->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 14]);

        $list = DB::table('items')->pluck('name')->toArray();
        sort($list);
        $this->assertEquals(4, count($list));
        $this->assertEquals(['fork', 'knife', 'spoon', 'spoon'], $list);

        $list = DB::table('items')->pluck('type', 'name')->toArray();
        $this->assertEquals(3, count($list));
        $this->assertEquals(['knife' => 'sharp', 'fork' => 'sharp', 'spoon' => 'round'], $list);
    }

    /**
     * @group QueryBuilderTest
     */
    public function testIncrement()
    {
        DB::table('users')->insert([
            ['name' => 'John Doe', 'age' => 30, 'note' => 'adult'],
            ['name' => 'Jane Doe', 'age' => 10, 'note' => 'minor'],
            ['name' => 'Robert Roe', 'age' => null],
            ['name' => 'Mark Moe'],
        ]);

        $user = DB::table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(30, $user['age']);

        DB::table('users')->where('name', 'John Doe')->increment('age');
        $user = DB::table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(31, $user['age']);

        DB::table('users')->where('name', 'John Doe')->decrement('age');
        $user = DB::table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(30, $user['age']);

        DB::table('users')->where('name', 'John Doe')->increment('age', 5);
        $user = DB::table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(35, $user['age']);

        DB::table('users')->where('name', 'John Doe')->decrement('age', 5);
        $user = DB::table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(30, $user['age']);

        DB::table('users')->where('name', 'Jane Doe')->increment('age', 10, ['note' => 'adult']);
        $user = DB::table('users')->where('name', 'Jane Doe')->first();
        $this->assertEquals(20, $user['age']);
        $this->assertEquals('adult', $user['note']);

        DB::table('users')->where('name', 'John Doe')->decrement('age', 20, ['note' => 'minor']);
        $user = DB::table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(10, $user['age']);
        $this->assertEquals('minor', $user['note']);

        DB::table('users')->increment('age');
        $user = DB::table('users')->where('name', 'John Doe')->first();
        $this->assertEquals(11, $user['age']);
        $user = DB::table('users')->where('name', 'Jane Doe')->first();
        $this->assertEquals(21, $user['age']);
        $user = DB::table('users')->where('name', 'Robert Roe')->first();
        $this->assertEquals(1, $user['age']);
    }
}
