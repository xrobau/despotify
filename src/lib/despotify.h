#ifndef LIBDESPOTIFY_H
#define LIBDESPOTIFY_H

#include <pthread.h>
#include <stdbool.h>
#include <time.h>

#define STRING_LENGTH 256
#define MAX_SEARCH_RESULTS 100 /* max search results per request */
#define SUBSTREAM_SIZE (100 * 1024)
#define TIMEOUT 10 /* timeout in seconds */

struct ds_track
{
    bool has_meta_data;
    bool playable;
    bool geo_restricted;
    unsigned char track_id[33];
    unsigned char file_id[41];
    unsigned int file_bitrate;
    unsigned char album_id[33];
    unsigned char cover_id[41];
    unsigned char *key;
    
    char *allowed;
    char *forbidden;

    char title[STRING_LENGTH];
    struct ds_artist* artist;
    char album[STRING_LENGTH];
    int length;
    int tracknumber;
    int year;
    float popularity;
    struct ds_track *next; /* in case of multiple tracks
                           in an album or playlist struct */
};

struct ds_search_result
{
    unsigned char query[STRING_LENGTH];
    unsigned char suggestion[STRING_LENGTH];
    int total_artists;
    int total_albums;
    int total_tracks;
    struct ds_artist *artists;
    struct ds_album *albums;
    struct ds_track *tracks;
    struct ds_playlist *playlist;
};

struct ds_playlist
{
    char name[STRING_LENGTH];
    char author[STRING_LENGTH];
    unsigned char playlist_id[35];
    bool is_collaborative;
    int num_tracks;
    unsigned int revision;
    unsigned int checksum;
    struct ds_track *tracks;
    struct ds_playlist *next; /* in case of multiple playlists in the root list */
};

struct ds_album
{
    char name[STRING_LENGTH];
    char id[33];
    char artist[STRING_LENGTH];
    char artist_id[33];
    char cover_id[41];
    float popularity;
    struct ds_album* next;
};

struct ds_album_browse
{
    char name[STRING_LENGTH];
    char id[33];
    int num_tracks;
    struct ds_track* tracks;
    int year;
    char cover_id[41];
    float popularity;
    struct ds_album_browse* next; /* in case of multiple albums in an artist struct */
};

struct ds_artist
{
    char name[STRING_LENGTH];
    char id[33];
    char portrait_id[41];
    float popularity;
    struct ds_artist* next;
};

struct ds_artist_browse
{
    char name[STRING_LENGTH];
    char id[33];
    char* text;
    char portrait_id[41];
    char genres[STRING_LENGTH];
    char years_active[STRING_LENGTH];
    float popularity;
    int num_albums;
    struct ds_album_browse* albums;
};

struct ds_user_info
{
    char username[STRING_LENGTH];
    char country[4];
    char type[16];
    time_t expiry;
    char server_host[STRING_LENGTH];
    short server_port;
    time_t last_ping;
};

enum ds_link_type {
    LINK_TYPE_INVALID,
    LINK_TYPE_ALBUM,
    LINK_TYPE_ARTIST,
    LINK_TYPE_PLAYLIST,
    LINK_TYPE_SEARCH,
    LINK_TYPE_TRACK
};

struct ds_link
{
    const char* uri;
    const char* arg;
    enum ds_link_type type;
};

struct ds_snd_buffer /* internal use */
{
    int length; /* Total length of this buffer */
    int cmd; /* command for the player... 1 == DATA, 0 == INIT */
    int consumed; /* Number of bytes consumed */
    unsigned char* ptr;

    struct ds_snd_buffer* next;
};

struct ds_snd_fifo /* internal use */
{
    pthread_mutex_t lock;
    pthread_cond_t cs;
    int totbytes; /* Total number of bytes added to queue */
    int maxbytes; /* Maximum size of queue */
    int watermark; /* Low watermark */
    int lastcmd;

    struct ds_snd_buffer* start;	/* First buffer */
    struct ds_snd_buffer* end;	/* Last buffer */
};

struct ds_pcm_data
{
    int samplerate;
    int channels;
    int len;
    char buf[4096];
};

struct despotify_session
{
    bool initialized;
    struct session* session;
    struct ds_user_info* user_info;
    const char *last_error;

    /* AES CTR state */
    struct {
        unsigned int  state[4 * (10 + 1)];
        unsigned char IV[16];
        unsigned char keystream[16];
    } aes;

    pthread_t thread;

    struct ds_album_browse* album_browse;
    struct ds_artist_browse* artist_browse;
    struct ds_track* track;
    struct ds_playlist* playlist;
    struct buf* response;
    int offset;

    /* client/lib synchronization */
    pthread_mutex_t sync_mutex;
    pthread_cond_t  sync_cond;

    bool list_of_lists;
    bool play_as_list;
    bool high_bitrate;
    bool use_cache;

    /* client callback */
    void(*client_callback)(struct despotify_session* session,
                           int signal,
                           void* signal_data,
                           void* client_callback_data);
    void *client_callback_data;

    /* internal data: */
    void* vf;
    void* mf;
    struct ds_snd_fifo* fifo;
    int dlstate;
    int errorcount;
    bool dlabort;
};

/* callback signals */
enum {
    DESPOTIFY_NEW_TRACK = 1,
    /* Called when a new track starts playing, such as after
       despotify_play() or on track transition.

       data: pointer to 'struct track' */

    DESPOTIFY_TIME_TELL,
    /* Called regularly to allow client to display elapsed time.

       Note that it may be called more often than you want to redraw. Use a
       suitable filter.

       data: pointer to 'double' indicated elapsed time in seconds */

    DESPOTIFY_END_OF_PLAYLIST,
    /* Called after last track in playlist has finished playing */

    DESPOTIFY_TRACK_PLAY_ERROR,
    /* Called if an error occurred while attempting to play the track.
     
       E.g. Georestrictions. */
};

/* Global init / deinit library. */
bool despotify_init(void);
bool despotify_cleanup(void);

/* Session stuff. */
struct despotify_session *despotify_init_client(void(*callback)(struct despotify_session*, int, void*, void*), void*, bool, bool);

void despotify_exit(struct despotify_session *ds);

bool despotify_authenticate(struct despotify_session *ds, 
                            const char *user, 
                            const char *password);

#define despotify_change_user(session, user, password) \
                    do { \
                        despotify_close(session); \
                        (session) = despotify_new_session(); \
                        despotify_authenticate(session, user, password); \
                    } while (0)

void despotify_set_buffer_size(struct despotify_session* ds, int size);
void despotify_set_watermark(struct despotify_session* ds, int watermark);

void despotify_free(struct despotify_session *ds, bool should_disconnect);

const char *despotify_get_error(struct despotify_session *ds);

/* Browse functions.  */
struct ds_artist_browse* despotify_get_artist(struct despotify_session* ds,
                                           char* artist_id);
struct ds_album_browse* despotify_get_album(struct despotify_session* ds,
                                         char* album_id);
struct ds_track* despotify_get_tracks(struct despotify_session* ds, char* track_ids[], int num_tracks);
struct ds_track* despotify_get_track(struct despotify_session* ds, char* track_id);
void* despotify_get_image(struct despotify_session* ds,
                          char* image_id, int* len);

void despotify_free_artist_browse(struct ds_artist_browse* a);
void despotify_free_album_browse(struct ds_album_browse* a);
void despotify_free_track(struct ds_track* t);

/* We need to determine if there is any / enough info to warrant this:
 * user despotify_get_user_info(struct despotify_session *ds); */

/* Search */
struct ds_search_result* despotify_search(struct despotify_session *ds,
                                       const char *searchtext, int maxresults);
struct ds_search_result* despotify_search_more(struct despotify_session *ds,
                                            struct ds_search_result* search,
                                            int offset, int maxresults);
void despotify_free_search(struct ds_search_result *search);


/* Playlist handling. */
struct ds_playlist* despotify_get_playlist(struct despotify_session *ds,
                                        char* playlist_id, bool cache_do_store);
struct ds_playlist* despotify_get_stored_playlists(struct despotify_session *ds);
bool despotify_rename_playlist(struct despotify_session *ds,
                               struct ds_playlist *playlist, char *name);
bool despotify_set_playlist_collaboration(struct despotify_session *ds,
                                          struct ds_playlist *playlist,
                                          bool collaborative);
void despotify_free_playlist(struct ds_playlist* playlist);

/* Playback control. */

bool despotify_play(struct despotify_session *ds,
                    struct ds_track *song,
                    bool play_as_list);
void despotify_next(struct despotify_session *ds);
bool despotify_stop(struct despotify_session *ds);

struct ds_track* despotify_get_current_track(struct despotify_session* ds);

int despotify_get_pcm(struct despotify_session*, struct ds_pcm_data*);
int despotify_get_raw(struct despotify_session*, char* buf, int length);

/* URI utils */
struct ds_link* despotify_link_from_uri(const char* uri);

struct ds_album_browse* despotify_link_get_album(struct despotify_session* ds, struct ds_link* link);
struct ds_artist_browse* despotify_link_get_artist(struct despotify_session* ds, struct ds_link* link);
struct ds_playlist* despotify_link_get_playlist(struct despotify_session* ds, struct ds_link* link);
struct ds_search_result* despotify_link_get_search(struct despotify_session* ds, struct ds_link* link);
struct ds_track* despotify_link_get_track(struct despotify_session* ds, struct ds_link* link);

void despotify_free_link(struct ds_link* link);

char* despotify_album_to_uri(struct ds_album_browse* album, char* dest);
char* despotify_artist_to_uri(struct ds_artist_browse* album, char* dest);
char* despotify_playlist_to_uri(struct ds_playlist* album, char* dest);
char* despotify_search_to_uri(struct ds_search_result* album, char* dest);
char* despotify_track_to_uri(struct ds_track* album, char* dest);

void despotify_id2uri(const char* id, char* uri);
void despotify_uri2id(const char* uri, char* id);

/* internal functions */
int despotify_snd_read_stream(struct despotify_session* ds);

#endif
