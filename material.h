#ifndef PHP_RAYLIB_MATERIAL_H
#define PHP_RAYLIB_MATERIAL_H

#include "include/hashmap.h"
#include "shader.h"
#include "materialmap.h"

extern zend_class_entry * php_raylib_material_ce;

// External Free Objects - Prevent Circular Dependency
extern void php_raylib_shader_free_storage(zend_object *object);
//TODO: Support array/hash
//extern void php_raylib_materialmap_array_free_storage(zend_object *object);

extern void php_raylib_material_free_storage(zend_object * object);

extern zend_object * php_raylib_material_new(zend_class_entry * ce);

extern zend_object * php_raylib_material_new_ex(zend_class_entry *ce, zend_object *orig);

extern zend_object_handlers php_raylib_material_object_handlers;

typedef enum {
    RL_MATERIAL_IS_POINTER,
    RL_MATERIAL_IS_VALUE
} RLMaterialDataType;

typedef union {
    Material *p;
    Material v;
} MaterialUnion;

struct RL_Material {
    unsigned int id;
    char *guid;
    MaterialUnion data;
    RLMaterialDataType type;
    unsigned refCount;
    unsigned char deleted;
};

static struct RL_Material **RL_Material_Object_List;
static hashmap *RL_Material_Object_Map;

char* RL_Material_Hash_Id(char *str, size_t size);
struct RL_Material* RL_Material_Create();
void RL_Material_Delete(struct RL_Material* object, int index);
void RL_Material_Free(struct RL_Material* object);

typedef struct _php_raylib_material_object {
    struct RL_Material *material;
    HashTable *prop_handler;
    zval shader;
    zval maps;
    // Cannot support primitive data structure like float (an array) as zval
    // zval params;
    zend_object std;
} php_raylib_material_object;

static inline php_raylib_material_object *php_raylib_material_fetch_object(zend_object *obj) {
    return (php_raylib_material_object *)((char *)obj - XtOffsetOf(php_raylib_material_object, std));
}

static inline Material *php_raylib_material_fetch_data(php_raylib_material_object *obj) {
    Material *my_material;
    if (obj->material->type == RL_MATERIAL_IS_POINTER) {
        my_material = obj->material->data.p;
    } else {
        my_material = &obj->material->data.v;
    }

    return my_material;
}

#define Z_MATERIAL_OBJ_P(zv) php_raylib_material_fetch_object(Z_OBJ_P(zv));

void php_raylib_material_startup(INIT_FUNC_ARGS);

#endif //PHP_RAYLIB_MATERIAL_H
