<?php

namespace Tests\Feature;

use App\Contracts\PokemonCatalog;
use App\Models\PokemonCollectionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_renders_trainer_card_with_collection_data_and_party(): void
    {
        $user = User::factory()->create(['name' => 'Red', 'id' => 1]);
        PokemonCollectionItem::factory()->for($user)->create(['pokemon_id' => 25, 'is_favorite' => true]);
        PokemonCollectionItem::factory()->for($user)->create(['pokemon_id' => 6, 'is_favorite' => false]);

        $pikachu = $this->mockPokemon(25, 'Pikachu', ['electric'], 320);
        $charizard = $this->mockPokemon(6, 'Charizard', ['fire', 'flying'], 534);

        $this->mock(PokemonCatalog::class, function (MockInterface $mock) use ($pikachu, $charizard): void {
            $mock->shouldReceive('findMany')->once()->andReturn([$pikachu, $charizard]);
            $mock->shouldReceive('types')->once()->andReturn(['electric', 'fire', 'flying']);
        });

        $response = $this->actingAs($user)->get(route('profile.show'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('trainerCard.trainer_number', 'TR-00001')
            ->where('trainerCard.trainer_name', 'Red')
            ->where('trainerCard.rank', 'Novato')
            ->where('trainerCard.total_pokemon', 2)
            ->where('trainerCard.favorites_count', 1)
            ->where('trainerCard.signature_pokemon.id', 25)
            ->has('trainerCard.party', 2));
    }

    public function test_user_isolation_ensures_trainer_card_only_counts_own_collection(): void
    {
        $userA = User::factory()->create(['id' => 10]);
        $userB = User::factory()->create(['id' => 20]);

        PokemonCollectionItem::factory()->for($userA)->count(3)->create();
        PokemonCollectionItem::factory()->for($userB)->count(10)->create();

        $this->mock(PokemonCatalog::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findMany')->andReturn([]);
            $mock->shouldReceive('types')->andReturn([]);
        });

        $responseA = $this->actingAs($userA)->get(route('profile.show'));
        $responseA->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('trainerCard.total_pokemon', 3)
            ->where('trainerCard.rank', 'Novato'));

        $responseB = $this->actingAs($userB)->get(route('profile.show'));
        $responseB->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('trainerCard.total_pokemon', 10)
            ->where('trainerCard.rank', 'Entrenador'));
    }

    public function test_trainer_bio_uses_ai_response_and_caches_it(): void
    {
        $user = User::factory()->create();
        PokemonCollectionItem::factory()->for($user)->create(['pokemon_id' => 6]);

        $charizard = $this->mockPokemon(6, 'Charizard', ['fire'], 534);

        $this->mock(PokemonCatalog::class, function (MockInterface $mock) use ($charizard): void {
            $mock->shouldReceive('findMany')->andReturn([$charizard]);
            $mock->shouldReceive('types')->andReturn(['fire']);
        });

        Http::fake([
            '*/trainer/profile-bio' => Http::response([
                'headline' => 'Maestro Ígneo',
                'description' => 'Especialista en Pokémon de fuego de alto poder.',
            ], 200),
        ]);

        $response = $this->actingAs($user)->get(route('profile.show'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('trainerCard.headline', 'Maestro Ígneo')
            ->where('trainerCard.description', 'Especialista en Pokémon de fuego de alto poder.')
            ->where('trainerCard.is_ai_generated', true));

        // Second request should use cache and not hit AI endpoint again
        Http::fake([
            '*/trainer/profile-bio' => Http::response([], 500),
        ]);

        $response2 = $this->actingAs($user)->get(route('profile.show'));
        $response2->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('trainerCard.headline', 'Maestro Ígneo'));
    }

    public function test_user_can_regenerate_trainer_bio(): void
    {
        $user = User::factory()->create();
        PokemonCollectionItem::factory()->for($user)->create(['pokemon_id' => 25]);

        $pikachu = $this->mockPokemon(25, 'Pikachu', ['electric'], 320);

        $this->mock(PokemonCatalog::class, function (MockInterface $mock) use ($pikachu): void {
            $mock->shouldReceive('findMany')->andReturn([$pikachu]);
            $mock->shouldReceive('types')->andReturn(['electric']);
        });

        Http::fake([
            '*/trainer/profile-bio' => Http::response([
                'headline' => 'Rayo Veloz',
                'description' => 'Entrenador eléctrico con gran dinamismo.',
            ], 200),
        ]);

        $response = $this->actingAs($user)->post(route('profile.bio.regenerate'));

        $response->assertRedirect();
        $response->assertSessionHas('flash.message', 'Identidad de entrenador actualizada con Pika IA.');
    }

    public function test_user_can_upload_and_delete_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $photo = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAs($user)->put(route('user-profile-information.update'), [
            'name' => 'Gary Oak',
            'email' => $user->email,
            'photo' => $photo,
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);

        // Delete photo
        $deleteResponse = $this->actingAs($user)->delete(route('current-user-photo.destroy'));
        $deleteResponse->assertRedirect();
        $user->refresh();
        $this->assertNull($user->profile_photo_path);
    }

    /**
     * @param  list<string>  $types
     * @return array<string, mixed>
     */
    private function mockPokemon(int $id, string $name, array $types, int $bst): array
    {
        return [
            'id' => $id,
            'name' => strtolower($name),
            'display_name' => $name,
            'image' => "https://example.com/{$id}.png",
            'types' => $types,
            'height_m' => 1.0,
            'weight_kg' => 20.0,
            'abilities' => [],
            'stats' => ['hp' => (int) ($bst / 6), 'speed' => (int) ($bst / 6)],
        ];
    }
}
