<?php

namespace App\Enums;

enum AssistantActionType: string
{
    case AddPokemon = 'add_pokemon';
    case RemovePokemon = 'remove_pokemon';
}
