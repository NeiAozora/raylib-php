#ifndef PHP_RAYLIB_MUSIC_H
#define PHP_RAYLIB_MUSIC_H

#include "include/hashmap.h"
#include "audiostream.h"

extern zend_class_entry * php_raylib_music_ce;

// External Free Objects - Prevent Circular Dependency
extern void php_raylib_audiostream_free_storage(zend_object *object);

extern void php_raylib_music_free_storage(zend_object * object);

extern zend_object * php_raylib_music_new(zend_class_entry * ce);

extern zend_object * php_raylib_music_new_ex(zend_class_entry *ce, zend_object *orig);

extern zend_object_handlers php_raylib_music_object_handlers;

struct RL_Music {
    unsigned int id;
    char *guid;
    Music data;
    unsigned refCount;
    unsigned char deleted;
};

static struct RL_Music **RL_Music_Object_List;
static hashmap *RL_Music_Object_Map;

char* RL_Music_Hash_Id(char *str, size_t size);
struct RL_Music* RL_Music_Create();
void RL_Music_Delete(struct RL_Music* object, int index);
void RL_Music_Free(struct RL_Music* object);

typedef struct _php_raylib_music_object {
    struct RL_Music *music;
    HashTable *prop_handler;
    zval stream;
    zend_object std;
} php_raylib_music_object;

static inline php_raylib_music_object *php_raylib_music_fetch_object(zend_object *obj) {
    return (php_raylib_music_object *)((char *)obj - XtOffsetOf(php_raylib_music_object, std));
}

#define Z_MUSIC_OBJ_P(zv) php_raylib_music_fetch_object(Z_OBJ_P(zv));

void php_raylib_music_startup(INIT_FUNC_ARGS);

#endif //PHP_RAYLIB_MUSIC_H
