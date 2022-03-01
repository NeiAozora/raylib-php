#ifndef PHP_RAYLIB_VRSTEREOCONFIG_H
#define PHP_RAYLIB_VRSTEREOCONFIG_H

#include "raylib-matrix.h"
#include "raylib-matrix.h"

extern zend_class_entry * php_raylib_vrstereoconfig_ce;

// External Free Objects - Prevent Circular Dependency
//TODO: Support array/hash
//extern void php_raylib_matrix_array_free_storage(zend_object *object);

extern void php_raylib_vrstereoconfig_free_storage(zend_object * object);

extern zend_object * php_raylib_vrstereoconfig_new(zend_class_entry * ce);

extern zend_object * php_raylib_vrstereoconfig_new_ex(zend_class_entry *ce, zend_object *orig);

extern zend_object_handlers php_raylib_vrstereoconfig_object_handlers;

typedef struct _php_raylib_vrstereoconfig_object {
    VrStereoConfig vrstereoconfig;
    HashTable *prop_handler;
php_raylib_matrix_object *projection;
php_raylib_matrix_object *viewoffset;
    zend_object std;
} php_raylib_vrstereoconfig_object;

static inline php_raylib_vrstereoconfig_object *php_raylib_vrstereoconfig_fetch_object(zend_object *obj) {
    return (php_raylib_vrstereoconfig_object *)((char *)obj - XtOffsetOf(php_raylib_vrstereoconfig_object, std));
}

#define Z_VRSTEREOCONFIG_OBJ_P(zv) php_raylib_vrstereoconfig_fetch_object(Z_OBJ_P(zv));

void php_raylib_vrstereoconfig_startup(INIT_FUNC_ARGS);

static void php_raylib_vrstereoconfig_update_intern(php_raylib_vrstereoconfig_object *intern);

#endif //PHP_RAYLIB_VRSTEREOCONFIG_H
