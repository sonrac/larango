<?php
namespace sonrac\Arango\tests;

use sonrac\Arango\Collection;
use sonrac\Arango\Eloquent\Model;
use sonrac\Arango\tests\models\Book;
use sonrac\Arango\tests\models\Item;
use sonrac\Arango\tests\models\Soft;
use sonrac\Arango\tests\models\User;

class ModelTest extends BaseTestCase
{
    public function tearDown()
    {
        User::truncate();
        Soft::truncate();
        Book::truncate();
        Item::truncate();
    }

    public function testNewModel()
    {
        $user = new User;
        $this->assertInstanceOf('sonrac\Arango\Eloquent\Model', $user);
        $this->assertInstanceOf('sonrac\Arango\Connection', $user->getConnection());
        $this->assertEquals(false, $user->exists);
        $this->assertEquals('users', $user->getTable());
        $this->assertEquals('users', $user->getCollection());
        $this->assertEquals('_key', $user->getKeyName());
    }

    public function testInsert()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;

        $user->save();

        $this->assertEquals(true, $user->exists);
        $this->assertEquals(1, User::count());


        $this->assertTrue(isset($user->_key));
        $this->assertNotEquals('', (string) $user->_key);
        $this->assertNotEquals(0, strlen((string) $user->_key));
        $this->assertInstanceOf('Carbon\Carbon', $user->created_at);

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals(35, $user->age);
    }

    public function testUpdate()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $check = User::find($user->_key);

        $check->age = 36;
        $check->save();

        $this->assertEquals(true, $check->exists);
        $this->assertInstanceOf('Carbon\Carbon', $check->created_at);
        $this->assertInstanceOf('Carbon\Carbon', $check->updated_at);
        $this->assertEquals(1, User::count());

        $this->assertEquals('John Doe', $check->name);
        $this->assertEquals(36, $check->age);

        $user->update(['age' => 20]);

        $check = User::find($user->_key);
        $this->assertEquals(20, $check->age);
    }

    public function testDelete()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $this->assertEquals(true, $user->exists);
        $this->assertEquals(1, User::count());

        $user->delete();

        $this->assertEquals(0, User::count());
    }

    public function testAll()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $user = new User;
        $user->name = 'Jane Doe';
        $user->title = 'user';
        $user->age = 32;
        $user->save();

        $all = User::all();

        $this->assertEquals(2, count($all));
        $this->assertContains('John Doe', $all->pluck('name'));
        $this->assertContains('Jane Doe', $all->pluck('name'));
    }

    public function testFind()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $id = $user->_key;
        $check = User::find($id);

        $this->assertInstanceOf(Model::class, $check);
        $this->assertEquals(true, $check->exists);
        $this->assertEquals($user->_key, $check->_key);

        $this->assertEquals('John Doe', $check->name);
        $this->assertEquals(35, $check->age);
    }

    public function testGet()
    {
        User::insert([
            ['name' => 'John Doe'],
            ['name' => 'Jane Doe'],
        ]);

        $users = User::get();
        $this->assertEquals(2, count($users));
        $this->assertInstanceOf(Model::class, $users[0]);
    }

    public function testFirst()
    {
        User::insert([
            ['name' => 'John Doe'],
        ]);

        $user = User::first();
        $this->assertInstanceOf(Model::class, $user);
        $this->assertEquals('John Doe', $user->name);
    }

    public function testNoDocument()
    {
        $items = Item::where('name', 'nothing')->get();
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $items);
        $this->assertEquals(0, $items->count());

        $item = Item::where('name', 'nothing')->first();
        $this->assertEquals(null, $item);

        $item = Item::find('51c33d8981fec6813e00000a');
        $this->assertEquals(null, $item);
    }

    public function testFindOrfail()
    {
        $this->expectException('Illuminate\Database\Eloquent\ModelNotFoundException');
        User::findOrfail('51c33d8981fec6813e00000a');
    }

    public function testCreate()
    {
        $user = User::create(['name' => 'Jane Poe']);

        $this->assertInstanceOf(Model::class, $user);
        $this->assertEquals(true, $user->exists);
        $this->assertEquals('Jane Poe', $user->name);

        $check = User::where('name', 'Jane Poe')->first();
        $this->assertEquals($user->_key, $check->_key);
    }

    public function testDestroy()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        User::destroy((string) $user->_key);

        $this->assertEquals(0, User::count());
    }

    public function testTouch()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $old = $user->updated_at;

        sleep(1);
        $user->touch();
        $check = User::find($user->_key);

        $this->assertNotEquals($old, $check->updated_at);
    }

    public function testSoftDelete()
    {
        Soft::create(['name' => 'John Doe']);
        Soft::create(['name' => 'Jane Doe']);

        $this->assertEquals(2, Soft::count());

        $user = Soft::where('name', 'John Doe')->first();
        $this->assertEquals(true, $user->exists);
        $this->assertEquals(false, $user->trashed());
        $this->assertNull($user->deleted_at);

        $user->delete();
        $this->assertEquals(true, $user->trashed());
        $this->assertNotNull($user->deleted_at);

        $user = Soft::where('name', 'John Doe')->first();
        $this->assertNull($user);

        $this->assertEquals(1, Soft::count());
        $this->assertEquals(2, Soft::withTrashed()->count());

        $user = Soft::withTrashed()->where('name', 'John Doe')->first();
        $this->assertNotNull($user);
        $this->assertInstanceOf('Carbon\Carbon', $user->deleted_at);
        $this->assertEquals(true, $user->trashed());

        $user->restore();
        $this->assertEquals(2, Soft::count());
    }

    public function testScope()
    {
        Item::insert([
            ['name' => 'knife', 'type' => 'sharp'],
            ['name' => 'spoon', 'type' => 'round'],
        ]);

        $sharp = Item::sharp()->get();
        $this->assertEquals(1, $sharp->count());
    }

    public function testToArray()
    {
        $item = Item::create(['name' => 'fork', 'type' => 'sharp']);

        $array = $item->toArray();
        $keys = array_keys($array);
        sort($keys);
        $this->assertEquals(['_key', 'created_at', 'name', 'type', 'updated_at'], $keys);
        $this->assertTrue(is_string($array['created_at']));
        $this->assertTrue(is_string($array['updated_at']));
        $this->assertTrue(is_string($array['_key']));
    }
}
