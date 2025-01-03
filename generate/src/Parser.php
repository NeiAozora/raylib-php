<?php

namespace Raylib\Parser;

use Raylib\Parser\Generate\CFunction;
use Raylib\Parser\Generate\ObjectClone;
use Raylib\Parser\Generate\ObjectConstructor;
use Raylib\Parser\Generate\ObjectFreePropertyHandler;
use Raylib\Parser\Generate\ObjectFreeStorage;
use Raylib\Parser\Generate\ObjectGetGarbageCollector;
use Raylib\Parser\Generate\ObjectGetProperties;
use Raylib\Parser\Generate\ObjectGetPropertyPointerPointer;
use Raylib\Parser\Generate\ObjectHasProperty;
use Raylib\Parser\Generate\ObjectMethods;
use Raylib\Parser\Generate\ObjectNew;
use Raylib\Parser\Generate\ObjectNewEx;
use Raylib\Parser\Generate\ObjectPropertyGetters;
use Raylib\Parser\Generate\ObjectPropertyHandler;
use Raylib\Parser\Generate\ObjectPropertySetters;
use Raylib\Parser\Generate\ObjectPropertyReader;
use Raylib\Parser\Generate\ObjectPropertyWriter;
use Raylib\Parser\Generate\ObjectRL;
use Raylib\Parser\Generate\ObjectStartup;
use Raylib\Parser\Generate\ObjectStruct;

class Parser
{
    /**
     * @var array{structs:array,enums:array,functions:array}
     */
    private array $data;

    public function load(string $filepath)
    {
        $json       = file_get_contents($filepath);
        $this->data = json_decode($json, true);
    }

    public function generateStructs(): void
    {
        $structs   = $this->data['structs'];
        $enums     = $this->data['enums'];
        $functions = $this->data['functions'];

        $aliases = [
            'Texture'       => ['Texture2D', 'TextureCubemap'],
            'RenderTexture' => ['RenderTexture2D'],
            'Camera3D'      => ['Camera'],
            'Vector4'       => ['Quaternion'],
        ];


        /** @var \Raylib\Parser\Struct[] $structObjs */
        $structObjs = [];
        /** @var \Raylib\Parser\Struct[] $structsByType */
        $structsByType = [];
        /** @var array{name:string,description:string,fields:array} $structInfo */
        foreach ($structs as $structInfo) {
            $struct                       = new Struct($aliases, $structInfo);
            $structObjs[]                 = $struct;
            $structsByType[$struct->name] = $struct;
        }

        $enumObjs    = [];
        $enumsByType = [];
        /** @var array{name:string,description:string,values:array} $enumInfo */
        foreach ($enums as $enumInfo) {
            $enum                     = new Enum($enumInfo['name'], $enumInfo['description'], $enumInfo['values']);
            $enumObjs[]               = $enum;
            $enumsByType[$enum->name] = $enum;
        }

        $functionObjs    = [];
        $functionsByType = [];
        foreach ($functions as $functionInfo) {
            $function = new Func($aliases, $functionInfo);
            if (in_array($function->name, [
                'GetRenderWidth',
                'GetRenderHeight',
                'TraceLog', //-- Not Yet Supported
                'SetTraceLogCallback', //-- Not Yet Supported
                'SetSaveFileDataCallback', //-- Not Yet Supported
                'SetLoadFileTextCallback', //-- Not Yet Supported
                'SetSaveFileTextCallback', //-- Not Yet Supported
                'SetLoadFileDataCallback', //-- Not Yet Supported
                'SaveFileData', //-- Not Yet Supported
                'MemFree', //-- Not Yet Supported
                'SetWindowOpacity', //-- Not Yet Supported
                'GetPixelColor', //-- Not Yet Supported
                'SetPixelColor', //-- Not Yet Supported
                'GenImageFontAtlas', //-- Not Yet Supported
                'UpdateMeshBuffer', //-- Not Yet Supported
                'SetShaderValue', //-- Not Yet Supported
                'SetShaderValueV', //-- Not Yet Supported
                'UpdateTexture', //-- Not Yet Supported
                'UpdateTextureRec', //-- Not Yet Supported
                'UpdateSound', //-- Not Yet Supported
                'UpdateAudioStream', //-- Not Yet Supported
                'SetAudioStreamCallback', //-- Not Yet Supported
                'AttachAudioStreamProcessor', //-- Not Yet Supported
                'DetachAudioStreamProcessor', //-- Not Yet Supported
                'AttachAudioMixedProcessor', //-- Not Yet Supported
                'DetachAudioMixedProcessor', //-- Not Yet Supported
            ])) {
                $function->unsupported = true;
            }

            $functionObjs[]                   = $function;
            $functionsByType[$function->name] = $function;
        }

        //-- Add in aliases
        $structsByType['Texture2D']                = clone $structsByType['Texture'];
        $structsByType['Texture2D']->alias         = 'Texture';
        $structsByType['Texture2D']->isAlias       = 'Texture';
        $structsByType['TextureCubemap']           = clone $structsByType['Texture'];
        $structsByType['TextureCubemap']->alias    = 'Texture';
        $structsByType['TextureCubemap']->isAlias  = 'Texture';
        $structsByType['RenderTexture2D']          = clone $structsByType['RenderTexture'];
        $structsByType['RenderTexture2D']->alias   = 'RenderTexture';
        $structsByType['RenderTexture2D']->isAlias = 'RenderTexture';
        $structsByType['Camera']                   = clone $structsByType['Camera3D'];
        $structsByType['Camera']->alias            = 'Camera3D';
        $structsByType['Camera']->isAlias          = 'Camera3D';
        $structsByType['Quaternion']               = clone $structsByType['Vector4'];
        $structsByType['Quaternion']->alias        = 'Vector4';
        $structsByType['Quaternion']->isAlias      = 'Vector4';

        /** @var array{name:string,description:string,fields:array} $struct */
        foreach ($structObjs as $struct) {

            $structFileName = '' . $struct->nameLower;
            $cFileName      = __DIR__ . '/../../' . $structFileName . '.c';
            $hFileName      = __DIR__ . '/../../' . $structFileName . '.h';

            $generateStructC      = $this->generateStructC($functionObjs, $structsByType, $struct, []);
            $generateStructHeader = $this->generateStructHeader($structsByType, $struct, []);

            file_put_contents($cFileName, implode("\n", $generateStructC));
            file_put_contents($hFileName, implode("\n", $generateStructHeader));
        }

        $cFileName = __DIR__ . '/../../raylib.c';
        $generateC = $this->generateBaseC($functionsByType, $enumObjs, $structsByType, []);
        file_put_contents($cFileName, implode("\n", $generateC));

        $hFileName = __DIR__ . '/../../php_raylib.h';
        $generateH = $this->generateBaseH($enumObjs, $structsByType, []);
        file_put_contents($hFileName, implode("\n", $generateH));

        $configM4File = __DIR__ . '/../../config.m4';
        $generateM4   = $this->generateM4($enumObjs, $structsByType, []);
        file_put_contents($configM4File, implode("\n", $generateM4));

        $configW32File = __DIR__ . '/../../config.w32';
        $generateW32   = $this->generateW32($enumObjs, $structsByType, []);
        file_put_contents($configW32File, implode("\n", $generateW32));

        (new Stub())->generate($functionObjs, $structsByType, $enumObjs);

        foreach ($functionsByType as $function) {
            if ($function->unsupported) {
                echo $function->name, ' is not yet supported', PHP_EOL;
            }
        }
    }

    /**
     * @param \Raylib\Parser\Func[]   $functions
     * @param \Raylib\Parser\Enum[]   $enums
     * @param \Raylib\Parser\Struct[] $structs
     * @param array                   $input
     *
     * @return array
     */
    public function generateBaseC(array $functions, array $enums, array $structs, array $input): array
    {
        $input[] = '/*';
        $input[] = '  +----------------------------------------------------------------------+';
        $input[] = '  | PHP Version 7                                                        |';
        $input[] = '  +----------------------------------------------------------------------+';
        $input[] = '  | Copyright (c) 1997-2018 The PHP Group                                |';
        $input[] = '  +----------------------------------------------------------------------+';
        $input[] = '  | This source file is subject to version 3.01 of the PHP license,      |';
        $input[] = '  | that is bundled with this package in the file LICENSE, and is        |';
        $input[] = '  | available through the world-wide-web at the following url:           |';
        $input[] = '  | http://www.php.net/license/3_01.txt                                  |';
        $input[] = '  | If you did not receive a copy of the PHP license and are unable to   |';
        $input[] = '  | obtain it through the world-wide-web, please send a note to          |';
        $input[] = '  | license@php.net so we can mail you a copy immediately.               |';
        $input[] = '  +----------------------------------------------------------------------+';
        $input[] = '  | Author: Joseph Montanez                                              |';
        $input[] = '  +----------------------------------------------------------------------+';
        $input[] = '*/';
        $input[] = '';
        $input[] = '/* $Id$ */';
        $input[] = '';
        $input   = Helper::win32PreventCollision($input);
        $input[] = '#ifdef HAVE_CONFIG_H';
        $input[] = '#include "config.h"';
        $input[] = '#endif';
        $input[] = '';
        $input[] = '#include "php_raylib.h"';

        foreach ($structs as $struct) {
            $input[] = '#include "' . str_replace('_array', '', $struct->nameLower) . '.h"';
        }
        $input[] = '';

        $input[] = 'ZEND_BEGIN_ARG_INFO_EX(arginfo_confirm_raylib_compiled, 0, 0, 0)';
        $input[] = 'ZEND_END_ARG_INFO()';
        $input[] = 'PHP_FUNCTION(confirm_raylib_compiled)';
        $input[] = '{';
        $input[] = '    char *arg = NULL;';
        $input[] = '    size_t arg_len;';
        $input[] = '//    size_t arg_len, len;';
        $input[] = '    zend_string *strg;';
        $input[] = '';
        $input[] = '    if (zend_parse_parameters(ZEND_NUM_ARGS(), "s", &arg, &arg_len) == FAILURE) {';
        $input[] = '        return;';
        $input[] = '    }';
        $input[] = '';
        $input[] = '    strg = strpprintf(0, "Congratulations! You have successfully modified ext/%.78s/config.m4. Module %.78s is now compiled into PHP.", "raylib", arg);';
        $input[] = '';
        $input[] = '    RETURN_STR(strg);';
        $input[] = '}';
        $input[] = '';

        foreach ($functions as $function) {
            if ($function->unsupported) {
                continue;
            }
            $input = (new CFunction())->gerenate($function, $input, $structs);
        }


        $input[] = '/* {{{ PHP_MINIT_FUNCTION';
        $input[] = ' */';
        $input[] = 'PHP_MINIT_FUNCTION(raylib)';
        $input[] = '{';
        foreach ($structs as $struct) {
            $input[] = '    php_raylib_' . $struct->nameLower . '_startup(INIT_FUNC_ARGS_PASSTHRU);';
        }
        $input[] = '';

        $input[] = '    // raylib Config Flags';
        foreach ($enums as $enum) {
            foreach ($enum->values as $value) {
                $input[] = '    REGISTER_NS_LONG_CONSTANT("raylib\\\\' . $enum->name . '", "' . $value->name . '", ' . $value->name . ', CONST_CS | CONST_PERSISTENT);';
            }
        }
        $input[] = '';
        $input[] = '    return SUCCESS;';
        $input[] = '} /* }}} */';
        $input[] = '';


        $input[] = '/* {{{ PHP_MSHUTDOWN_FUNCTION';
        $input[] = ' */';
        $input[] = 'PHP_MSHUTDOWN_FUNCTION(raylib)';
        $input[] = '{';
        $input[] = '    /* uncomment this line if you have INI entries';
        $input[] = '    UNREGISTER_INI_ENTRIES();';
        $input[] = '    */';
        $input[] = '    return SUCCESS;';
        $input[] = '}';
        $input[] = '/* }}} */';
        $input[] = '';

        $input[] = '/* Remove if there\'s nothing to do at request start */';
        $input[] = '/* {{{ PHP_RINIT_FUNCTION';
        $input[] = ' */';
        $input[] = 'PHP_RINIT_FUNCTION(raylib)';
        $input[] = '{';
        $input[] = '#if defined(COMPILE_DL_RAYLIB) && defined(ZTS)';
        $input[] = '    ZEND_TSRMLS_CACHE_UPDATE();';
        $input[] = '#endif';
        $input[] = '    return SUCCESS;';
        $input[] = '}';
        $input[] = '/* }}} */';
        $input[] = '';

        $input[] = '/* Remove if there\'s nothing to do at request end */';
        $input[] = '/* {{{ PHP_RSHUTDOWN_FUNCTION';
        $input[] = ' */';
        $input[] = 'PHP_RSHUTDOWN_FUNCTION(raylib)';
        $input[] = '{';
        $input[] = '    return SUCCESS;';
        $input[] = '}';
        $input[] = '/* }}} */';
        $input[] = '';

        $input[] = '/* {{{ PHP_MINFO_FUNCTION';
        $input[] = ' */';
        $input[] = 'PHP_MINFO_FUNCTION(raylib)';
        $input[] = '{';
        $input[] = '    php_info_print_table_start();';
        $input[] = '    php_info_print_table_header(2, "raylib support", "enabled");';
        $input[] = '    php_info_print_table_end();';
        $input[] = '';
        $input[] = '    /* Remove comments if you have entries in php.ini';
        $input[] = '    DISPLAY_INI_ENTRIES();';
        $input[] = '    */';
        $input[] = '}';
        $input[] = '/* }}} */';

        // TODO: Map functions
        $input[] = '/* {{{ raylib_functions[]';
        $input[] = ' *';
        $input[] = ' * Every user visible function must have an entry in raylib_functions[].';
        $input[] = ' */';
        $input[] = 'const zend_function_entry raylib_functions[] = {';
        $input[] = '        ZEND_NS_FE("raylib", confirm_raylib_compiled, arginfo_confirm_raylib_compiled)';
        $input[] = '        // Misc. functions';
        foreach ($functions as $function) {
            if ($function->unsupported) {
                continue;
            }
            $input[] = sprintf(
                "        ZEND_FE(%s, arginfo_%s)",
                $function->rename ?? $function->name, $function->rename ?? $function->name
            );
        }
        $input[] = '        PHP_FE_END';
        $input[] = '};';
        $input[] = '/* }}} */';

        $input[] = '/* {{{ raylib_module_entry';
        $input[] = ' */';
        $input[] = 'zend_module_entry raylib_module_entry = {';
        $input[] = '    STANDARD_MODULE_HEADER,';
        $input[] = '    "raylib",';
        $input[] = '    raylib_functions,';
        $input[] = '    PHP_MINIT(raylib),';
        $input[] = '    PHP_MSHUTDOWN(raylib),';
        $input[] = '    PHP_RINIT(raylib),        /* Replace with NULL if there\'s nothing to do at request start */';
        $input[] = '    PHP_RSHUTDOWN(raylib),    /* Replace with NULL if there\'s nothing to do at request end */';
        $input[] = '    PHP_MINFO(raylib),';
        $input[] = '    PHP_RAYLIB_VERSION,';
        $input[] = '    STANDARD_MODULE_PROPERTIES';
        $input[] = '};';
        $input[] = '/* }}} */';
        $input[] = '';

        $input[] = '#ifdef COMPILE_DL_RAYLIB';
        $input[] = '#ifdef ZTS';
        $input[] = 'ZEND_TSRMLS_CACHE_DEFINE()';
        $input[] = '#endif';
        $input[] = 'ZEND_GET_MODULE(raylib)';
        $input[] = '#endif';
        $input[] = '';

        return $input;
    }


    /**
     * @param \Raylib\Parser\Struct[] $structsByType
     * @param \Raylib\Parser\Struct   $struct
     * @param array                   $input
     *
     * @return array
     */
    public function generateStructHeader(array $structsByType, Struct $struct, array $input): array
    {
        $input[] = '#ifndef PHP_RAYLIB_' . $struct->nameUpper . '_H';
        $input[] = '#define PHP_RAYLIB_' . $struct->nameUpper . '_H';
        $input[] = '';

        $input[] = '#include "include/hashmap.h"';
        //-- Include headers for references below
        $includes = [];
        foreach ($struct->nonPrimitiveFields() as $field) {
            $fieldType     = $field->type;
            $fieldTypeName = $field->typeName;
            if ($field->isArray) {
                $fieldType     = str_replace(['*', ' '], '', $fieldType);
                $fieldTypeName = str_replace(['*', ' '], '', $fieldTypeName);
            }

            if (!isset($structsByType[$fieldType])) {
                continue;
            }

            if ($structsByType[$fieldType]->isAlias) {
                $aliasStruct = $structsByType[$structsByType[$fieldType]->alias];
                $includes[]     = '#include "' . str_replace('_array', '', $aliasStruct->nameLower) . '.h"';
            } else {
                $includes[] = '#include "' . str_replace('_array', '', $fieldTypeName) . '.h"';
            }
        }
        $includes = array_unique($includes);
        foreach ($includes as $include) {
            $input []= $include;
        }

        $input[] = '';

        //-- PHP Class Entry
        $input[] = 'extern zend_class_entry * php_raylib_' . $struct->nameLower . '_ce;';
        $input[] = '';

        //-- include external object free to avoid circular dependency
        $input[] = '// External Free Objects - Prevent Circular Dependency';
        foreach ($struct->fieldsByType() as $field) {
            if ($field->isPrimitive) {
                continue;
            }

            if ($field->isArray) {
                $input[] = '//TODO: Support array/hash';
                $input[] = '//extern void php_raylib_' . $field->typeName . '_free_storage(zend_object *object);';
            } elseif (isset($structsByType[$field->typePlain]) && $structsByType[$field->typePlain]->isAlias) {
                $aliasStruct = $structsByType[$structsByType[$field->type]->alias];

                $input[] = 'extern void php_raylib_' . $aliasStruct->nameLower . '_free_storage(zend_object *object);';

            } else {
                $input[] = 'extern void php_raylib_' . $field->typeName . '_free_storage(zend_object *object);';
            }

        }
        $input[] = '';

        //-- PHP Free (When object is unset)
        $input[] = 'extern void php_raylib_' . $struct->nameLower . '_free_storage(zend_object * object);';
        $input[] = '';

        //-- Internal Constructor
        $input[] = 'extern zend_object * php_raylib_' . $struct->nameLower . '_new(zend_class_entry * ce);';
        $input[] = '';

        //-- Internal Constructor Used For "clone" in PHP
        $input[] = 'extern zend_object * php_raylib_' . $struct->nameLower . '_new_ex(zend_class_entry *ce, zend_object *orig);';
        $input[] = '';

        //-- Handlers to register how the PHP object will be interacted with
        $input[] = 'extern zend_object_handlers php_raylib_' . $struct->nameLower . '_object_handlers;';
        $input[] = '';

        $input = (new ObjectRL())->generate($structsByType, $struct, $input);

        //-- PHP Object
        $input = (new ObjectStruct())->generate($structsByType, $struct, $input);

        $input[] = 'static inline php_raylib_' . $struct->nameLower . '_object *php_raylib_' . $struct->nameLower . '_fetch_object(zend_object *obj) {';
        $input[] = '    return (php_raylib_' . $struct->nameLower . '_object *)((char *)obj - XtOffsetOf(php_raylib_' . $struct->nameLower . '_object, std));';
        $input[] = '}';
        $input[] = '';

        $input[] = 'static inline ' . $struct->name . ' *php_raylib_' . $struct->nameLower . '_fetch_data(php_raylib_' . $struct->nameLower . '_object *obj) {';
        $input[] = '    ' . $struct->name . ' *my_' . $struct->nameLower . ';';
        $input[] = '    if (obj->' . $struct->nameLower . '->type == RL_' . $struct->nameUpper . '_IS_POINTER) {';
        $input[] = '        my_' . $struct->nameLower . ' = obj->' . $struct->nameLower . '->data.p;';
        $input[] = '    } else {';
        $input[] = '        my_' . $struct->nameLower . ' = &obj->' . $struct->nameLower . '->data.v;';
        $input[] = '    }';
        $input[] = '';
        $input[] = '    return my_' . $struct->nameLower . ';';
        $input[] = '}';
        $input[] = '';


        $input[] = '#define Z_' . $struct->nameUpper . '_OBJ_P(zv) php_raylib_' . $struct->nameLower . '_fetch_object(Z_OBJ_P(zv));';
        $input[] = '';

        $input[] = 'void php_raylib_' . $struct->nameLower . '_startup(INIT_FUNC_ARGS);';
        $input[] = '';

        $input[] = '#endif //PHP_RAYLIB_' . $struct->nameUpper . '_H';
        $input[] = '';

        return $input;
    }

    /**
     * @param \Raylib\Parser\Func[]   $functions
     * @param \Raylib\Parser\Struct[] $structsByType
     * @param \Raylib\Parser\Struct   $struct
     * @param array                   $input
     *
     * @return array
     */
    public function generateStructC(array $functions, array $structsByType, Struct $struct, array $input): array
    {
        //-- Window API Work Around
        $input = Helper::win32PreventCollision($input);

        $input[] = '#include "php.h"';
        $input[] = '#undef LOG_INFO';
        $input[] = '#undef LOG_WARNING';
        $input[] = '#undef LOG_DEBUG';
        $input[] = '';

        $input[] = '#include "raylib.h"';
        $input[] = '#include "include/hashmap.h"';
        $input[] = '';

        $input[] = '#ifdef PHP_WIN32';
        $input[] = '    #include <Wincrypt.h>';
        $input[] = '#endif';
        $input[] = '';

        $headers = [];
        foreach ($struct->nonPrimitiveFields() as $field) {
            $fieldType     = $field->type;
            $fieldTypeName = $field->typeName;
            if ($field->isArray || $field->isPointer) {
                $fieldType     = rtrim(str_replace(['*', ' '], '', $fieldType), '_');
                $fieldTypeName = rtrim(str_replace(['*', ' '], '', $fieldTypeName), '_');
            }

            if (!isset($structsByType[$fieldType])) {
                echo $fieldType, ' skipped', PHP_EOL;
                continue;
            }

            $line = '#include "' . str_replace('_array', '', $fieldTypeName) . '.h"';
            if (!isset($headers[$line])) {
                $headers[$line] = 1;
                $input[]        = $line;
            }

        }
        $input[] = '';

        //-- Include core struct header
        $input[] = '#include "' . $struct->nameLower . '.h"';
        $input[] = '';


        $input[] = '//-- Custom RayLib Struct Containers';
        $input[] = 'static unsigned int RL_' . $struct->nameUpper . '_OBJECT_ID = 0;';
        $input[] = 'static unsigned char RL_' . $struct->nameUpper . '_INIT = 0;';
        $input[] = 'static const unsigned int RL_' . $struct->nameUpper . '_MAX_OBJECTS = 999999;';
        $input[] = '';
        $input[] = 'char* RL_' . $struct->name . '_Hash_Id(char *str, size_t size) {';
        $input[] = '    const char charset[] = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";';
        $input[] = '    const int charset_size = sizeof(charset) - 1;';
        $input[] = '    for (size_t i = 0; i < size - 1; i++) {';
        $input[] = '#ifdef PHP_WIN32';
        $input[] = '        // On Windows, use CryptGenRandom to generate random bytes';
        $input[] = '        HCRYPTPROV hCryptProv;';
        $input[] = '        if (!CryptAcquireContext(&hCryptProv, NULL, NULL, PROV_RSA_FULL, CRYPT_VERIFYCONTEXT)) {';
        $input[] = '            fprintf(stderr, "CryptAcquireContext failed (%lu)\n", GetLastError());';
        $input[] = '            return NULL;';
        $input[] = '        }';
        $input[] = '        if (!CryptGenRandom(hCryptProv, 1, (BYTE *)&str[i])) {';
        $input[] = '            fprintf(stderr, "CryptGenRandom failed (%lu)\n", GetLastError());';
        $input[] = '            return NULL;';
        $input[] = '        }';
        $input[] = '        CryptReleaseContext(hCryptProv, 0);';
        $input[] = '#else';
        $input[] = '        // On other platforms, use arc4random to generate random bytes';
        $input[] = '        str[i] = charset[arc4random_uniform(charset_size)];';
        $input[] = '#endif';
        $input[] = '    }';
        $input[] = '    str[size-1] = \'\0\';';
        $input[] = '    return str;';
        $input[] = '}';
        $input[] = '';
        $input[] = 'struct RL_' . $struct->name . '* RL_' . $struct->name . '_Create() {';
        $input[] = '    //-- Create the initial data structures';
        $input[] = '    if (RL_' . $struct->nameUpper . '_INIT == 0) {';
        $input[] = '        RL_' . $struct->name . '_Object_List = (struct RL_' . $struct->name . '**) malloc(0);';
        $input[] = '        RL_' . $struct->name . '_Object_Map = hashmap_create();';
        $input[] = '        RL_' . $struct->nameUpper . '_INIT = 1;';
        $input[] = '    }';
        $input[] = '';
        $input[] = '    //-- Create the container object';
        $input[] = '    struct RL_' . $struct->name . '* object = (struct RL_' . $struct->name . '*) malloc(sizeof(struct RL_' . $struct->name . '));';
        $input[] = '    object->id = RL_' . $struct->nameUpper . '_OBJECT_ID++;';
        $input[] = '    object->guid = calloc(33, sizeof(char));';
        $input[] = '    object->guid = RL_' . $struct->name . '_Hash_Id(object->guid, sizeof(object->guid)); // Generate hash ID';
        // Or list this?
        // RL_Vector3_Hash_Id(object->guid, 33); // Generate hash ID
        $input[] = '    object->data.v = (' . $struct->name . ') {0};';
        $input[] = '    object->type = RL_' . $struct->nameUpper . '_IS_VALUE;';
        $input[] = '    object->refCount = 1;';
        $input[] = '    object->deleted = 0;';
        $input[] = '';
        $input[] = '    //-- Push to the dynamic array list';
        $input[] = '    RL_' . $struct->name . '_Object_List = (struct RL_' . $struct->name . '**) realloc(RL_' . $struct->name . '_Object_List, RL_' . $struct->nameUpper . '_OBJECT_ID * sizeof(struct RL_' . $struct->name . '*));';
        $input[] = '    RL_' . $struct->name . '_Object_List[object->id] = object;';
        $input[] = '';
        $input[] = '    //-- Add to hashmap';
        $input[] = '    hashmap_set(RL_' . $struct->name . '_Object_Map, object->guid, sizeof(object->guid) - 1, object);';
        $input[] = '';
        $input[] = '    return object;';
        $input[] = '}';
        $input[] = '';
        $input[] = 'void RL_' . $struct->name . '_Delete(struct RL_' . $struct->name . '* object, int index) {';
        $input[] = '    if (index < 0 || index >= RL_' . $struct->nameUpper . '_OBJECT_ID) {';
        $input[] = '        // Error: invalid index';
        $input[] = '        return;';
        $input[] = '    }';
        $input[] = '';
        $input[] = '    hashmap_remove(RL_' . $struct->name . '_Object_Map, object->guid, sizeof(object->guid) -1);';
        $input[] = '';
        $input[] = '    // Free the memory for the element being deleted';
        $input[] = '    free(RL_' . $struct->name . '_Object_List[index]);';
        $input[] = '';
        $input[] = '    // Shift the remaining elements over by one';
        $input[] = '    memmove(&RL_' . $struct->name . '_Object_List[index], &RL_' . $struct->name . '_Object_List[index + 1], (RL_' . $struct->nameUpper . '_OBJECT_ID - index - 1) * sizeof(struct RL_' . $struct->name . ' *));';
        $input[] = '';
        $input[] = '    // Decrement the count and resize the array';
        $input[] = '    RL_' . $struct->nameUpper . '_OBJECT_ID--;';
        $input[] = '    RL_' . $struct->name . '_Object_List = (struct RL_' . $struct->name . ' **)realloc(RL_' . $struct->name . '_Object_List, (RL_' . $struct->nameUpper . '_OBJECT_ID) * sizeof(struct RL_' . $struct->name . ' *));';
        $input[] = '}';
        $input[] = '';
        $input[] = 'void RL_' . $struct->name . '_Free(struct RL_' . $struct->name . '* object) {';
        $input[] = '    free(object);';
        $input[] = '}';
        $input[] = '';


        $input[] = '//------------------------------------------------------------------------------------------------------';
        $input[] = '//-- raylib ' . $struct->name . ' PHP Custom Object';
        $input[] = '//------------------------------------------------------------------------------------------------------';
        $input[] = 'zend_object_handlers php_raylib_' . $struct->nameLower . '_object_handlers;';
        $input[] = '';

        $input[] = 'static HashTable php_raylib_' . $struct->nameLower . '_prop_handlers;';
        $input[] = '';

        foreach ($struct->fieldsByType() as $field) {
            $phpReturnType = Helper::toPhpType($field);
            if ($field->isArray) {

            }
            $input[] = 'typedef ' . $phpReturnType . ' (*raylib_' . $struct->nameLower . '_read_' . $field->typeName . '_t)(php_raylib_' . $struct->nameLower . '_object *obj);';
            $input[] = 'typedef int (*raylib_' . $struct->nameLower . '_write_' . $field->typeName . '_t)(php_raylib_' . $struct->nameLower . '_object *obj,  zval *value);';
            $input[] = '';
        }

        //--------------------------------------------------------------------------------------------------------------
        //-- Object Property Handler
        // This defines property types both read and write
        //--------------------------------------------------------------------------------------------------------------
        // Type Definition
        $input[] = 'typedef struct _raylib_' . $struct->nameLower . '_prop_handler {';
//        if ($struct->nameLower === 'vrdeviceinfo') {
//            var_dump($struct);
//            exit;
//        }
        foreach ($struct->fieldsByType() as $type) {
            $fieldName  = $type->fieldName;
            $fieldName  = str_replace(['*', ' '], '', $fieldName);
            $fieldName  = str_replace('__', '_', $fieldName);
            $structName = str_replace(['*', ' '], '', $struct->nameLower);
            $structName = str_replace('__', '_', $structName);

            $input[] = '    raylib_' . $structName . '_read_' . $fieldName . '_t read_' . $fieldName . '_func;';
            $input[] = '    raylib_' . $structName . '_write_' . $fieldName . '_t write_' . $fieldName . '_func;';
        }
        $input[] = '} raylib_' . $struct->nameLower . '_prop_handler;';
        $input[] = '';


        // Function Handler - Setups object property type handlers
        $input = (new ObjectPropertyHandler())->generate($structsByType, $struct, $input);

        //-- Get Property Pointer Pointer - No idea what this is for, but needed
        $input = (new ObjectGetPropertyPointerPointer())->generate($structsByType, $struct, $input);

        //-- Property Reader - Routes to property handle to read from respective property
        $input = (new ObjectPropertyReader())->generate($struct, $input);

        //-- Property Writer - Routes to property handle to write to respective property
        $input = (new ObjectPropertyWriter())->generate($struct, $input);

        //-- Has Property - used to check if a property on the object exists
        $input = (new ObjectHasProperty())->generate($struct, $input);

        //-- Has Property - used to check if a property on the object exists
        $input = (new ObjectGetGarbageCollector())->generate($struct, $input);

        //-- Get Properties
        $input = (new ObjectGetProperties())->generate($struct, $input);

        //-- Free/Unset Storage
        $input = (new ObjectFreeStorage())->generate($struct, $input);

        //-- Free Prop Handlers
        $input = (new ObjectFreePropertyHandler())->generate($struct, $input);

        //-- Free/Unset Storage
        $input = (new ObjectNewEx())->generate($structsByType, $struct, $input);

        //-- Object New
        $input = (new ObjectNew())->generate($struct, $input);

        //-- Clone
        $input = (new ObjectClone())->generate($struct, $input);

        //-- Object Constructor
        $input = (new ObjectConstructor())->generate($functions, $structsByType, $struct, $input);

        $colors = [
            ['LIGHTGRAY', 200, 200, 200, 255],   // Light Gray
            ['GRAY', 130, 130, 130, 255],   // Gray
            ['DARKGRAY', 80, 80, 80, 255],      // Dark Gray
            ['YELLOW', 253, 249, 0, 255],     // Yellow
            ['GOLD', 255, 203, 0, 255],     // Gold
            ['ORANGE', 255, 161, 0, 255],     // Orange
            ['PINK', 255, 109, 194, 255],   // Pink
            ['RED', 230, 41, 55, 255],     // Red
            ['MAROON', 190, 33, 55, 255],     // Maroon
            ['GREEN', 0, 228, 48, 255],      // Green
            ['LIME', 0, 158, 47, 255],      // Lime
            ['DARKGREEN', 0, 117, 44, 255],      // Dark Green
            ['SKYBLUE', 102, 191, 255, 255],   // Sky Blue
            ['BLUE', 0, 121, 241, 255],     // Blue
            ['DARKBLUE', 0, 82, 172, 255],      // Dark Blue
            ['PURPLE', 200, 122, 255, 255],   // Purple
            ['VIOLET', 135, 60, 190, 255],    // Violet
            ['DARKPURPLE', 112, 31, 126, 255],    // Dark Purple
            ['BEIGE', 211, 176, 131, 255],   // Beige
            ['BROWN', 127, 106, 79, 255],    // Brown
            ['DARKBROWN', 76, 63, 47, 255],      // Dark Brown
            ['WHITE', 255, 255, 255, 255],   // White
            ['BLACK', 0, 0, 0, 255],         // Black
            ['BLANK', 0, 0, 0, 0],           // Blank (Transparent)
            ['MAGENTA', 255, 0, 255, 255],     // Magenta
            ['RAYWHITE', 245, 245, 245, 255],   // My own White (raylib logo)
        ];

        $colorMethods = [];
        if ($struct->name === 'Color') {
            foreach ($colors as $color) {
                $input          = (new ObjectConstructor())->generateColor($color, $input);
                $colorMethods[] = 'PHP_ME(Color, ' . $color[0] . ', arginfo_color_' . $color[0] . ', ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)';
            }
        }

        //-- Object Getters
        $input = (new ObjectPropertyGetters())->generate($structsByType, $struct, $input);

        //-- Object Getters
        $input = (new ObjectPropertySetters())->generate($structsByType, $struct, $input);

        //-- Object Methods
        $input = (new ObjectMethods())->generate($struct, $colorMethods, $input);

        //-- Object Setup
        $input = (new ObjectStartup())->generate($struct, $input);

        return $input;
    }

    /**
     * @param \Raylib\Parser\Enum[]   $enums
     * @param \Raylib\Parser\Struct[] $structs
     * @param array                   $input
     *
     * @return array
     */
    public function generateBaseH(array $enums, array $structs, array $input): array
    {
        $input[] = '/*';
        $input[] = '  +----------------------------------------------------------------------+';
        $input[] = '  | PHP Version 7                                                        |';
        $input[] = '  +----------------------------------------------------------------------+';
        $input[] = '  | Copyright (c) 1997-2018 The PHP Group                                |';
        $input[] = '  +----------------------------------------------------------------------+';
        $input[] = '  | This source file is subject to version 3.01 of the PHP license,      |';
        $input[] = '  | that is bundled with this package in the file LICENSE, and is        |';
        $input[] = '  | available through the world-wide-web at the following url:           |';
        $input[] = '  | http://www.php.net/license/3_01.txt                                  |';
        $input[] = '  | If you did not receive a copy of the PHP license and are unable to   |';
        $input[] = '  | obtain it through the world-wide-web, please send a note to          |';
        $input[] = '  | license@php.net so we can mail you a copy immediately.               |';
        $input[] = '  +----------------------------------------------------------------------+';
        $input[] = '  | Author: Joseph Montanez                                              |';
        $input[] = '  +----------------------------------------------------------------------+';
        $input[] = '*/';
        $input[] = '';
        $input[] = '/* $Id$ */';
        $input[] = '';
        $input[] = '#ifndef PHP_RAYLIB_H';
        $input[] = '#define PHP_RAYLIB_H';
        $input[] = '';
        $input[] = '#undef LOG_INFO';
        $input[] = '#undef LOG_WARNING';
        $input[] = '#undef LOG_DEBUG';
        $input[] = '';
        $input[] = '#include "raylib.h"';
        $input[] = '//#include "rlgl.h"';
        $input[] = '#include "php.h"';
        $input[] = '#include "php_ini.h"';
        $input[] = '#include "ext/standard/info.h"';
        $input[] = '#include "texture.h"';
        $input[] = '';
        $input[] = 'extern zend_module_entry raylib_module_entry;';
        $input[] = '#define phpext_raylib_ptr &raylib_module_entry';
        $input[] = '';
        $input[] = '#define PHP_RAYLIB_VERSION "2.0.0"';
        $input[] = '';

        foreach ($structs as $struct) {
            if ($struct->isAlias) {
                continue;
            }
            $input[] = 'zend_class_entry *php_raylib_' . $struct->nameLower . '_ce;';
        }
        $input[] = '';
        $input[] = '';
        $input[] = '#ifdef PHP_WIN32';
        $input[] = '#    define PHP_RAYLIB_API __declspec(dllexport)';
        $input[] = '#elif defined(__GNUC__) && __GNUC__ >= 4';
        $input[] = '#    define PHP_RAYLIB_API __attribute__ ((visibility("default")))';
        $input[] = '#else';
        $input[] = '#    define PHP_RAYLIB_API';
        $input[] = '#endif';
        $input[] = '';
        $input[] = '#ifdef ZTS';
        $input[] = '#include "TSRM.h"';
        $input[] = '#endif';
        $input[] = '';
        $input[] = '/*';
        $input[] = '      Declare any global variables you may need between the BEGIN';
        $input[] = '    and END macros here:';
        $input[] = '';
        $input[] = 'ZEND_BEGIN_MODULE_GLOBALS(raylib)';
        $input[] = '    zend_long  global_value;';
        $input[] = '    char *global_string;';
        $input[] = 'ZEND_END_MODULE_GLOBALS(raylib)';
        $input[] = '*/';
        $input[] = '';
        $input[] = '/* Always refer to the globals in your function as RAYLIB_G(variable).';
        $input[] = '   You are encouraged to rename these macros something shorter, see';
        $input[] = '   examples in any other php module directory.';
        $input[] = '*/';
        $input[] = '#define RAYLIB_G(v) ZEND_MODULE_GLOBALS_ACCESSOR(raylib, v)';
        $input[] = '';
        $input[] = '#if defined(ZTS) && defined(COMPILE_DL_RAYLIB)';
        $input[] = 'ZEND_TSRMLS_CACHE_EXTERN()';
        $input[] = '#endif';
        $input[] = '';
        $input[] = '#endif    /* PHP_RAYLIB_H */';
        $input[] = '';
        $input[] = '';
        $input[] = '/*';
        $input[] = ' * Local variables:';
        $input[] = ' * tab-width: 4';
        $input[] = ' * c-basic-offset: 4';
        $input[] = ' * End:';
        $input[] = ' * vim600: noet sw=4 ts=4 fdm=marker';
        $input[] = ' * vim<600: noet sw=4 ts=4';
        $input[] = ' */';

        return $input;
    }


    /**
     * @param \Raylib\Parser\Enum[]   $enums
     * @param \Raylib\Parser\Struct[] $structs
     * @param array                   $input
     *
     * @return array
     */
    public function generateM4(array $enums, array $structs, array $input): array
    {
        $input[] = 'dnl $Id$';
        $input[] = 'dnl config.m4 for extension raylib';
        $input[] = '';
        $input[] = 'dnl Comments in this file start with the string \'dnl\'.';
        $input[] = 'dnl Remove where necessary. This file will not work';
        $input[] = 'dnl without editing.';
        $input[] = '';
        $input[] = 'dnl If your extension references something external, use with:';
        $input[] = '';
        $input[] = 'PHP_ARG_WITH(raylib, for raylib support,';
        $input[] = 'Make sure that the comment is aligned:';
        $input[] = '[  --with-raylib             Include raylib support])';
        $input[] = '';
        $input[] = 'dnl Otherwise use enable:';
        $input[] = '';
        $input[] = 'PHP_ARG_ENABLE(raylib, whether to enable raylib support,';
        $input[] = 'Make sure that the comment is aligned:';
        $input[] = '[  --enable-raylib           Enable raylib support])';
        $input[] = '';
        $input[] = 'if test "$PHP_RAYLIB" != "no"; then';
        $input[] = '  dnl Write more examples of tests here...';
        $input[] = '';
        $input[] = '  # get library FOO build options from pkg-config output';
        $input[] = '  AC_PATH_PROG(PKG_CONFIG, pkg-config, no)';
        $input[] = '  AC_MSG_CHECKING(for libraylib)';
        $input[] = '  if test -x "$PKG_CONFIG" && $PKG_CONFIG --exists raylib; then';
        $input[] = '    if $PKG_CONFIG raylib --atleast-version 4.2.0; then';
        $input[] = '      LIBRAYLIB_CFLAGS=`$PKG_CONFIG raylib --cflags`';
        $input[] = '      LIBRAYLIB_LIBDIR=`$PKG_CONFIG raylib --libs`';
        $input[] = '      LIBRAYLIB_VERSON=`$PKG_CONFIG raylib --modversion`';
        $input[] = '      AC_MSG_RESULT(from pkgconfig: version $LIBRAYLIB_VERSON)';
        $input[] = '    else';
        $input[] = '      AC_MSG_ERROR(system libraylib is too old: version 4.2.0 required)';
        $input[] = '    fi';
        $input[] = '  else';
        $input[] = '    AC_MSG_ERROR(pkg-config not found)';
        $input[] = '  fi';
        $input[] = '  PHP_EVAL_LIBLINE($LIBRAYLIB_LIBDIR, RAYLIB_SHARED_LIBADD)';
        $input[] = '  PHP_EVAL_INCLINE($LIBRAYLIB_CFLAGS)';
        $input[] = '';
        $input[] = '  dnl # --with-raylib -> check with-path';
        $input[] = '  SEARCH_PATH="/usr/local /usr /opt/homebrew/Cellar/raylib/4.5.0 ./cmake-build-debug/_deps/raylib-src/src"     # you might want to change this';
        $input[] = '  SEARCH_FOR="/include/raylib.h"  # you most likely want to change this';
        $input[] = '  if test -r $PHP_RAYLIB/$SEARCH_FOR; then # path given as parameter';
        $input[] = '    RAYLIB_DIR=$PHP_RAYLIB';
        $input[] = '  else # search default path list';
        $input[] = '    AC_MSG_CHECKING([for raylib files in default path])';
        $input[] = '    for i in $SEARCH_PATH ; do';
        $input[] = '      if test -r $i/$SEARCH_FOR; then';
        $input[] = '        RAYLIB_DIR=$i';
        $input[] = '        AC_MSG_RESULT(found in $i)';
        $input[] = '      fi';
        $input[] = '    done';
        $input[] = '  fi';
        $input[] = '';
        $input[] = '  if test -z "$RAYLIB_DIR"; then';
        $input[] = '    AC_MSG_RESULT([not found])';
        $input[] = '    AC_MSG_ERROR([Please reinstall the raylib distribution])';
        $input[] = '  fi';
        $input[] = '';
        $input[] = '  dnl # --with-raylib -> add include path';
        $input[] = '  PHP_ADD_INCLUDE($RAYLIB_DIR/include)';
        $input[] = '';
        $input[] = '  dnl # --with-raylib -> check for lib and symbol presence';
        $input[] = '  LIBNAME=raylib # you may want to change this';
        $input[] = '  LIBSYMBOL=SetMouseScale # you most likely want to change this';
        $input[] = '';
        $input[] = '  PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,';
        $input[] = '  [';
        $input[] = '    PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $RAYLIB_DIR/$PHP_LIBDIR, RAYLIB_SHARED_LIBADD)';
        $input[] = '    AC_DEFINE(HAVE_RAYLIBLIB,1,[ ])';
        $input[] = '  ],[';
        $input[] = '    AC_MSG_ERROR([wrong raylib lib version or lib not found])';
        $input[] = '  ],[';
        $input[] = '    -L$RAYLIB_DIR/$PHP_LIBDIR -lm';
        $input[] = '  ])';
        $input[] = '';
        $input[] = '  PHP_SUBST(RAYLIB_SHARED_LIBADD)';
        $input[] = '';

        $files = ['raylib.c' => 1];
        foreach ($structs as $struct) {
            $files['' . $struct->nameLower . '.c'] = 1;
        }
        $files['include/hashmap.c'] = 1;

        $input[] = '  PHP_NEW_EXTENSION(raylib, ' . implode(' ', array_keys($files)) . ', $ext_shared,)';
        $input[] = 'fi';


        return $input;
    }


    /**
     * @param \Raylib\Parser\Enum[]   $enums
     * @param \Raylib\Parser\Struct[] $structs
     * @param array                   $input
     *
     * @return array
     */
    public function generateW32(array $enums, array $structs, array $input): array
    {

        $cFiles = ['raylib.c' => 1];
        $hFiles = ['php_raylib.h' => 1];
        foreach ($structs as $struct) {
            $cFiles[$struct->nameLower . '.c'] = 1;
            $hFiles[$struct->nameLower . '.h'] = 1;
        }
        $cFiles['includes\\\\hashmap.c'] = 1;
        $hFiles['includes\\\\hashmap.h'] = 1;
        $cFiles = array_keys($cFiles);
        $hFiles = array_keys($hFiles);


        $input[] = '// $Id$';
        $input[] = '// vim:ft=javascript';
        $input[] = '';
        $input[] = 'ARG_WITH("raylib", "for raylib support", "yes,shared");';
        $input[] = '';
        $input[] = 'if (PHP_RAYLIB != "no") {';
        $input[] = '    raylib_lib_paths = PHP_PHP_BUILD + "\\\\lib;.\\\\lib";';
        $input[] = '    raylib_include_paths = PHP_PHP_BUILD + "\\\\include;" + PHP_PHP_BUILD + "\\\\include\\\\GLFW;.\\\\include\\\\GLFW;.\\\\include";';
        $input[] = '';
        $input[] = '    raylib_check_lib = CHECK_LIB("raylib.lib", "raylib", raylib_lib_paths);';
        $input[] = '    raylib_check_header = CHECK_HEADER_ADD_INCLUDE("raylib.h", "CFLAGS_RAYLIB", raylib_include_paths);';
        $input[] = '    glfw_check_lib = CHECK_LIB("glfw3.lib", "raylib", raylib_lib_paths);';
        $input[] = '    glfw_check_header = CHECK_HEADER_ADD_INCLUDE("glfw3.h", "CFLAGS_RAYLIB", raylib_include_paths);';
        $input[] = '    glfw_check_header2 = CHECK_HEADER_ADD_INCLUDE("glfw3native.h", "CFLAGS_RAYLIB", raylib_include_paths);';
        $input[] = '    if (';
        $input[] = '        //-- Raylib';
        $input[] = '        raylib_check_lib &&';
        $input[] = '        raylib_check_header &&';
        $input[] = '        //-- GLFW';
        $input[] = '        glfw_check_lib &&';
        $input[] = '        glfw_check_header &&';
        $input[] = '        glfw_check_header2';
        $input[] = '        ) {';
        $input[] = '';
        $input[] = '        ADD_FLAG("LIBS_CLI", "raylib.lib glfw3.lib");';
        $input[] = '';
        $input[] = '        ADD_FLAG("LIBS_RAYLIB", "kernel32.lib user32.lib gdi32.lib winmm.lib winspool.lib comdlg32.lib advapi32.lib shell32.lib ole32.lib oleaut32.lib uuid.lib odbc32.lib odbccp32.lib")';
        $input[] = '';
        $input[] = '';
        $input[] = '        var raylib_sources = "' . implode(' ', $cFiles) . '";';
        $input[] = '        PHP_INSTALL_HEADERS("ext/raylib", "' . implode(' ', $hFiles) . '");';
        $input[] = '        EXTENSION("raylib", raylib_sources, true, "/DZEND_ENABLE_STATIC_TSRMLS_CACHE=1", "php_raylib.dll");';
        $input[] = '';
        $input[] = '    } else {';
        $input[] = '            WARNING("raylib not enabled; libraries not found");';
        $input[] = '            if (!raylib_check_lib) {';
        $input[] = '                WARNING("raylib lib missing" + PHP_PHP_BUILD);';
        $input[] = '            }';
        $input[] = '            if (!raylib_check_header) {';
        $input[] = '                WARNING("raylib header missing");';
        $input[] = '            }';
        $input[] = '            if (!glfw_check_lib) {';
        $input[] = '                WARNING("glfw lib missing");';
        $input[] = '            }';
        $input[] = '            if (!glfw_check_header) {';
        $input[] = '                WARNING("glfw header missing");';
        $input[] = '            }';
        $input[] = '    }';
        $input[] = '} else {';
        $input[] = '    WARNING("raylib not enabled; not set with");';
        $input[] = '}';


        return $input;
    }
}
