#ifndef PHP_RAYLIB_VECTOR3_H
#define PHP_RAYLIB_VECTOR3_H

#include "include/hashmap.h"

extern zend_class_entry * php_raylib_vector3_ce;

// External Free Objects - Prevent Circular Dependency

extern void php_raylib_vector3_free_storage(zend_object * object);

extern zend_object * php_raylib_vector3_new(zend_class_entry * ce);

extern zend_object * php_raylib_vector3_new_ex(zend_class_entry *ce, zend_object *orig);

extern zend_object_handlers php_raylib_vector3_object_handlers;

struct RL_Vector3 {
    unsigned int id;
    char *guid;
    Vector3 data;
    unsigned refCount;
    unsigned char deleted;
};

static struct RL_Vector3 **RL_Vector3_Object_List;
static hashmap *RL_Vector3_Object_Map;

char* RL_Vector3_Hash_Id(char *str, size_t size);
struct RL_Vector3* RL_Vector3_Create();
void RL_Vector3_Delete(struct RL_Vector3* object, int index);
void RL_Vector3_Free(struct RL_Vector3* object);

typedef struct _php_raylib_vector3_object {
    struct RL_Vector3 *vector3;
    HashTable *prop_handler;
    zend_object std;
} php_raylib_vector3_object;

static inline php_raylib_vector3_object *php_raylib_vector3_fetch_object(zend_object *obj) {
    return (php_raylib_vector3_object *)((char *)obj - XtOffsetOf(php_raylib_vector3_object, std));
}

#define Z_VECTOR3_OBJ_P(zv) php_raylib_vector3_fetch_object(Z_OBJ_P(zv));

void php_raylib_vector3_startup(INIT_FUNC_ARGS);

#endif //PHP_RAYLIB_VECTOR3_H
