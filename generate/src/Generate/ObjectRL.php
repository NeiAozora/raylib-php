<?php

namespace Raylib\Parser\Generate;

use Raylib\Parser\Struct;

/**
 * Generates PHP RL Object (Garbage Collection)
 */
class ObjectRL
{
    public function generate(array $structsByType, Struct $struct, array $input): array
    {
        $input[] = 'struct RL_' . $struct->name . ' {';
        $input[] = '    unsigned int id;';
        $input[] = '    char *guid;';
        $input[] = '    ' . $struct->name . ' data;';
        $input[] = '    unsigned refCount;';
        $input[] = '    unsigned char deleted;';
        $input[] = '};';
        $input[] = '';
        $input[] = 'static struct RL_' . $struct->name . ' **RL_' . $struct->name . '_Object_List;';
        $input[] = 'static hashmap *RL_' . $struct->name . '_Object_Map;';
        $input[] = '';
        $input[] = 'char* RL_' . $struct->name . '_Hash_Id(char *str, size_t size);';
        $input[] = 'struct RL_' . $struct->name . '* RL_' . $struct->name . '_Create();';
        $input[] = 'void RL_' . $struct->name . '_Delete(struct RL_' . $struct->name . '* object, int index);';
        $input[] = 'void RL_' . $struct->name . '_Free(struct RL_' . $struct->name . '* object);';
        $input[] = '';

        return $input;
    }
}
