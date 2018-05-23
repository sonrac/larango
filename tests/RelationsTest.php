<?php

namespace sonrac\Arango\tests;

use Illuminate\Support\Facades\DB;
use sonrac\Arango\Eloquent\Reletations\BelongsToMany;
use sonrac\Arango\tests\models\Book;
use sonrac\Arango\tests\models\Client;
use sonrac\Arango\tests\models\Group;
use sonrac\Arango\tests\models\Item;
use sonrac\Arango\tests\models\Photo;
use sonrac\Arango\tests\models\Role;
use sonrac\Arango\tests\models\User;

class RelationsTest extends BaseTestCase
{
    /**
     * @group RelationsTest
     */
    public function tearDown()
    {
        \Mockery::close();

        User::truncate();
        Client::truncate();
        Book::truncate();
        Item::truncate();
        Role::truncate();
        Client::truncate();
        Group::truncate();
        Photo::truncate();

        DB::table('client_user')->truncate();
        DB::table('group_user')->truncate();
    }

    /**
     * Test has many
     *
     * @group RelationsTest
     */
    public function testHasMany()
    {
        $author = User::create(['name' => 'George R. R. Martin']);
        Book::create(['title' => 'A Game of Thrones', 'author_id' => $author->_key]);
        Book::create(['title' => 'A Clash of Kings', 'author_id' => $author->_key]);

        $books = $author->books;
        $this->assertEquals(2, count($books));

        $user = User::create(['name' => 'John Doe']);
        Item::create(['type' => 'knife', 'user__key' => $user->_key]);
        Item::create(['type' => 'shield', 'user__key' => $user->_key]);
        Item::create(['type' => 'sword', 'user__key' => $user->_key]);
        Item::create(['type' => 'bag', 'user__key' => null]);

        $items = $user->items;
        $this->assertEquals(3, count($items));
    }

    /**
     * Test belongs to
     *
     * @group RelationsTest
     * @group testBelongsTo
     */
    public function testBelongsTo()
    {
        $user = User::create(['name' => 'George R. R. Martin']);
        Book::create(['title' => 'A Game of Thrones', 'author_id' => $user->_key]);
        $book = Book::create(['title' => 'A Clash of Kings', 'author_id' => $user->_key]);

        $author = $book->author;
        $this->assertEquals('George R. R. Martin', $author->name);

        $user = User::create(['name' => 'John Doe']);
        $item = Item::create(['type' => 'sword', 'user__key' => $user->_key]);

        $owner = $item->user;
        $this->assertEquals('John Doe', $owner->name);

        $book = Book::create(['title' => 'A Clash of Kings']);

        $this->assertEquals(null, $book->author);
    }

    /**
     * Test has one
     *
     * @group RelationsTest
     */
    public function testHasOne()
    {
        $user = User::create(['name' => 'John Doe']);
        Role::create(['type' => 'admin', 'user__key' => $user->_key]);

        $role = $user->role;
        $this->assertEquals('admin', $role->type);
        $this->assertEquals($user->_key, $role->user__key);

        $user = User::create(['name' => 'Jane Doe']);
        $role = new Role(['type' => 'user']);
        $user->role()->save($role);

        $role = $user->role;
        $this->assertEquals('user', $role->type);
        $this->assertEquals($user->_key, $role->user__key);

        $user = User::where('name', 'Jane Doe')->first();
        $role = $user->role;
        $this->assertEquals('user', $role->type);
        $this->assertEquals($user->_key, $role->user__key);
    }

    /**
     * Test with belongs to
     *
     * @group RelationsTest
     */
    public function testWithBelongsTo()
    {
        $user = User::create(['name' => 'John Doe']);
        Item::create(['type' => 'knife', 'user__key' => $user->_key]);
        Item::create(['type' => 'shield', 'user__key' => $user->_key]);
        Item::create(['type' => 'sword', 'user__key' => $user->_key]);
        Item::create(['type' => 'bag', 'user__key' => null]);

        $query = Item::with('user');
        $query = $query->orderBy('user__key', 'desc');
        $items = $query->get();

        $user = $items[0]->getRelation('user');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals(1, count($items[0]->getRelations()));
        $this->assertEquals(null, $items[3]->getRelation('user'));
    }

    /**
     * Test with has many
     *
     * @group RelationsTest
     */
    public function testWithHasMany()
    {
        $user = User::create(['name' => 'John Doe']);
        Item::create(['type' => 'knife', 'user__key' => $user->_key]);
        Item::create(['type' => 'shield', 'user__key' => $user->_key]);
        Item::create(['type' => 'sword', 'user__key' => $user->_key]);
        Item::create(['type' => 'bag', 'user__key' => null]);

        $user = User::with('items')->find($user->_key);

        $items = $user->getRelation('items');
        $this->assertEquals(3, count($items));
        $this->assertInstanceOf(Item::class, $items[0]);
    }

    /**
     * Test with has one
     *
     * @group RelationsTest
     */
    public function testWithHasOne()
    {
        $user = User::create(['name' => 'John Doe']);
        Role::create(['type' => 'admin', 'user__key' => $user->_key]);
        Role::create(['type' => 'guest', 'user__key' => $user->_key]);

        $user = User::with('role')->find($user->_key);

        $role = $user->getRelation('role');
        $this->assertInstanceOf(Role::class, $role);
    }

    /**
     * Test easy relation
     *
     * @group RelationsTest
     */
    public function testEasyRelation()
    {
        // Has Many
        $user = User::create(['name' => 'John Doe']);
        $item = Item::create(['type' => 'knife']);
        $user->items()->save($item);

        $user = User::find($user->_key);
        $items = $user->items;
        $this->assertEquals(1, count($items));
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertEquals($user->_key, $items[0]->user__key);

        // Has one
        $user = User::create(['name' => 'John Doe']);
        $role = Role::create(['type' => 'admin']);
        $user->role()->save($role);

        $user = User::find($user->_key);
        $role = $user->role;
        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('admin', $role->type);
        $this->assertEquals($user->_key, $role->user__key);
    }

    /**
     * @group RelationsTest
     * @group testBelongsToMany
     */
    public function testBelongsToMany()
    {
        /** @var User $user */
        $user = User::create(['name' => 'John Doe']);

        // Add 2 clients
        $user->clients()->save(new Client(['name' => 'Pork Pies Ltd.']));
        $user->clients()->create(['name' => 'Buffet Bar Inc.']); //first

        $user = User::with('clients')->find($user->_key);
        $client = Client::with('users')->where('name', 'Pork Pies Ltd.')->first();

        $clients = $user->getRelation('clients');
        $users = $client->getRelation('users');

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $users);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $clients);
        $this->assertInstanceOf(Client::class, $clients[0]);
        $this->assertInstanceOf(User::class, $users[0]);
        $this->assertCount(2, $user->clients);
        $this->assertCount(1, $client->users);

        // Now create a new user to an existing client
        $user = $client->users()->create(['name' => 'Jane Doe']); //second

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->clients);
        $this->assertInstanceOf(Client::class, $user->clients->first());
        $this->assertCount(1, $user->clients);

        // Get user and unattached client
        $user = User::where('name', '=', 'Jane Doe')->first();
        $client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

        // Check the models are what they should be
        $this->assertInstanceOf(Client::class, $client);
        $this->assertInstanceOf(User::class, $user);

        $this->assertCount(1, $user->clients);
        $this->assertCount(1, $client->users);

        // Attach the client to the user
        $user->clients()->attach($client);

        // Get the new user model
        $user = User::where('name', '=', 'Jane Doe')->first();
        $client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

        $this->assertCount(2, $user->clients);
        $this->assertCount(2, $client->users);

        // Detach clients from user
        $user->clients()->sync([]);

        // Get the new user model
        $user = User::where('name', '=', 'Jane Doe')->first();
        $client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

        $this->assertCount(0, $user->clients);
        $this->assertCount(1, $client->users);

        // Attach the client to the user via sync
        $user->clients()->sync([$client->getKey()]);

        // Get the new user model
        $user = User::where('name', '=', 'Jane Doe')->first();
        $client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

        // Assert they are attached
        $this->assertCount(1, $user->clients);
        $this->assertCount(2, $client->users);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManyAttachesExistingModels()
    {
        /** @var User $user */
        $user = User::create(['name' => 'John Doe', 'client_ids' => ['1234523']]);

        $clients = [
            Client::create(['name' => 'Pork Pies Ltd.'])->_key,
            Client::create(['name' => 'Buffet Bar Inc.'])->_key,
        ];

        $moreClients = [
            Client::create(['name' => 'synced Boloni Ltd.'])->_key,
            Client::create(['name' => 'synced Meatballs Inc.'])->_key,
        ];

        // Sync multiple records
        $user->clients()->sync($clients);

        $user = User::with('clients')->find($user->_key);

        $this->assertCount(2, $user->clients, 'Assert there are two client objects in the relationship');

        // Add more clients
        $user->clients()->sync($moreClients);

        // Refetch
        $user = User::with('clients')->find($user->getKey());

        $this->assertCount(2, $user->clients, 'Assert there are now still 2 client objects in the relationship');

        // Assert that the new relationships name start with synced
        $this->assertStringStartsWith('synced', $user->clients[0]->name);
        $this->assertStringStartsWith('synced', $user->clients[1]->name);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManySync()
    {
        // create test instances
        $user = User::create(['name' => 'John Doe']);
        $client1 = Client::create(['name' => 'Pork Pies Ltd.'])->_key;
        $client2 = Client::create(['name' => 'Buffet Bar Inc.'])->_key;
        $client3 = Client::create(['name' => 'Meatballs Inc.'])->_key;

        // Sync multiple
        $user->clients()->sync([$client1, $client2]);
        $this->assertCount(2, $user->clients);

        // Refresh user
        $user = User::where('name', '=', 'John Doe')->first();

        // Sync
        $user->clients()->sync([$client1, $client3]);

        // Expect two clients
        $this->assertCount(2, $user->clients);

        // Expect client1 and client3, but not client2
        $this->assertContains('Pork Pies Ltd.', [$user->clients[0]->name, $user->clients[1]->name]);
        $this->assertContains('Meatballs Inc.', [$user->clients[0]->name, $user->clients[1]->name]);
        $this->assertNotContains('Buffet Bar Inc.', [$user->clients[0]->name, $user->clients[1]->name]);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManyAttachArray()
    {
        $user = User::create(['name' => 'John Doe']);
        $client1 = Client::create(['name' => 'Test 1'])->_key;
        $client2 = Client::create(['name' => 'Test 2'])->_key;

        $user = User::where('name', '=', 'John Doe')->first();
        $user->clients()->attach([$client1, $client2]);
        $this->assertCount(2, $user->clients);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManyAttachEloquentCollection()
    {
        $user = User::create(['name' => 'John Doe']);
        $client1 = Client::create(['name' => 'Test 1']);
        $client2 = Client::create(['name' => 'Test 2']);
        $collection = new \Illuminate\Database\Eloquent\Collection([$client1, $client2]);

        $user = User::where('name', '=', 'John Doe')->first();
        $user->clients()->attach($collection);
        $this->assertCount(2, $user->clients);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManySyncAlreadyPresent()
    {
        $user = User::create(['name' => 'John Doe']);
        $client1 = Client::create(['name' => 'Test 1'])->_key;
        $client2 = Client::create(['name' => 'Test 2'])->_key;

        $user->clients()->sync([$client1, $client2]);
        $this->assertCount(2, $user->clients);

        $user = User::where('name', '=', 'John Doe')->first();
        $user->clients()->sync([$client1]);
        $this->assertCount(1, $user->clients);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManyBindings()
    {
        $user = User::create(['name' => 'John Doe']);
        $groupsRelation = $user->groups();

        $this->assertTrue($groupsRelation instanceof BelongsToMany, 'Assert that User->groups is a BelongsToManyRelation');
        $this->assertTrue(is_array($groupsRelation->getBindings()), 'Assert that bindings are an array');
        $this->assertTrue(is_array($groupsRelation->getRawBindings()), 'Assert that raw bindings are an array');
    }

    /**
     * @group RelationsTest
     */
    public function testMorph()
    {
        /** @var \User $user */
        $user = User::create(['name' => 'John Doe']);
        $client = Client::create(['name' => 'Jane Doe']);

        $photo = Photo::create(['url' => 'http://graph.facebook.com/john.doe/picture']);
        $photo = $user->photos()->save($photo);

        $this->assertEquals(1, $user->photos->count());
        $this->assertEquals($photo->id, $user->photos->first()->id);

        $user = User::find($user->_key);
        $this->assertEquals(1, $user->photos->count());
        $this->assertEquals($photo->id, $user->photos->first()->id);

        $photo = Photo::create(['url' => 'http://graph.facebook.com/jane.doe/picture']);
        $client->photo()->save($photo);

        $this->assertNotNull($client->photo);
        $this->assertEquals($photo->id, $client->photo->id);

        $client = Client::find($client->_key);
        $this->assertNotNull($client->photo);
        $this->assertEquals($photo->id, $client->photo->id);

        $photo = Photo::first();
        $this->assertEquals($photo->imageable->name, $user->name);

        $user = User::with('photos')->find($user->_key);
        $relations = $user->getRelations();
        $this->assertTrue(array_key_exists('photos', $relations));
        $this->assertEquals(1, $relations['photos']->count());

        $photos = Photo::with('imageable')->get();
        $relations = $photos[0]->getRelations();
        $this->assertTrue(array_key_exists('imageable', $relations));
        $this->assertInstanceOf('User', $photos[0]->imageable);

        $relations = $photos[1]->getRelations();
        $this->assertTrue(array_key_exists('imageable', $relations));
        $this->assertInstanceOf('Client', $photos[1]->imageable);
    }

    /**
     * @group RelationsTest
     */
    public function testHasManyHas()
    {
        $author1 = User::create(['name' => 'George R. R. Martin']);
        $author1->books()->create(['title' => 'A Game of Thrones', 'rating' => 5]);
        $author1->books()->create(['title' => 'A Clash of Kings', 'rating' => 5]);
        $author2 = User::create(['name' => 'John Doe']);
        $author2->books()->create(['title' => 'My book', 'rating' => 2]);
        User::create(['name' => 'Anonymous author']);
        Book::create(['title' => 'Anonymous book', 'rating' => 1]);

        $authors = User::has('books')->orderBy('name')->get();
        $this->assertCount(2, $authors);
        $this->assertEquals('George R. R. Martin', $authors[0]->name);
        $this->assertEquals('John Doe', $authors[1]->name);

        $authors = User::whereHas('books', function ($query) {
            $query->where('rating', 5);
        })->get();
        $this->assertCount(1, $authors);

        $authors = User::whereHas('books', function ($query) {
            $query->where('rating', '<', 5);
        })->get();
        $this->assertCount(1, $authors);
    }

    /**
     * @group RelationsTest
     */
    public function testHasOneHas()
    {
        $user1 = User::create(['name' => 'John Doe']);
        $user1->role()->create(['title' => 'admin']);
        $user2 = User::create(['name' => 'Jane Doe']);
        $user2->role()->create(['title' => 'reseller']);
        User::create(['name' => 'Mark Moe']);
        Role::create(['title' => 'Customer']);

        $users = User::has('role')->orderBy('name', 'DESC')->get();
        $this->assertCount(2, $users);
        $this->assertEquals('John Doe', $users[0]->name);
        $this->assertEquals('Jane Doe', $users[1]->name);
    }

    /**
     * @group RelationsTest
     */
    public function testDoubleSaveOneToMany()
    {
        $author = User::create(['name' => 'George R. R. Martin']);
        $book = Book::create(['title' => 'A Game of Thrones']);

        $author->books()->save($book);
        $author->books()->save($book);
        $author->save();
        $this->assertEquals(1, $author->books()->count());
        $this->assertEquals($author->_key, $book->author_id);

        $author = User::where('name', 'George R. R. Martin')->first();
        $book = Book::where('title', 'A Game of Thrones')->first();
        $this->assertEquals(1, $author->books()->count());
        $this->assertEquals($author->_key, $book->author_id);

        $author->books()->save($book);
        $author->books()->save($book);
        $author->save();
        $this->assertEquals(1, $author->books()->count());
        $this->assertEquals($author->_key, $book->author_id);
    }

    /**
     * @group RelationsTest
     */
    public function testDoubleSaveManyToMany()
    {
        $user = User::create(['name' => 'John Doe']);
        $client = Client::create(['name' => 'Admins']);

        $user->clients()->attach($client);
        $user->clients()->attach($client);
        $user->save();

        $this->assertEquals(2, $user->clients()->count());

        $user = User::where('name', 'John Doe')->first();
        $client = Client::where('name', 'Admins')->first();
        $this->assertEquals(2, $user->clients()->count());

        $user->clients()->save($client);
        $user->clients()->save($client);
        $user->save();
        $this->assertEquals(2, $user->clients()->count());
    }
}
