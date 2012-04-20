#
# $Id$
# 

unexport LDFLAGS CFLAGS

LIBDIR = ../../lib
LIB = $(LIBDIR)/libdespotify.la

NCURSES_INCLUDE ?= /usr/include/ncursesw

DESPOTIFY_OBJS = commands.o event.o main.o session.o ui.o ui_footer.o ui_help.o ui_log.o ui_sidebar.o ui_splash.o ui_tracklist.o

CFLAGS += -I$(LIBDIR) -I$(NCURSES_INCLUDE)
ifeq ($(shell uname -s),Darwin)
    DESPOTIFY_OBJS += coreaudio.o
    # The default ncurses library on Mac OS X supports wide characters
    # so force linking with the one in /usr/lib
    LDFLAGS = -L/usr/lib -lncurses -framework CoreAudio
else ifeq ($(shell uname -i),BCM2708)
    DESPOTIFY_OBJS += rpi_audio.o
    LDFLAGS += -lncursesw -L${RPI_SDK}/lib/ -lGLESv2 -lEGL -lopenmaxil -lbcm_host ${RPI_SDK}/src/hello_pi/libs/libilclient.a -lpthread
    CFLAGS += -DHAVE_LIBOPENMAX=2 -DOMX -DOMX_SKIP64BIT -ftree-vectorize -pipe -DUSE_EXTERNAL_OMX -DHAVE_LIBBCM_HOST -DUSE_EXTERNAL_LIBBCM_HOST -DUSE_VCHIQ_ARM -Wno-psabi -I${RPI_SDK}/include/ -I${RPI_SDK}/src/hello_pi/libs
else ifeq ($(shell uname -s),Linux)
    DESPOTIFY_OBJS += libao.o
    LDFLAGS += -lncursesw -lao
else
    LDFLAGS += -lncursesw
endif


.PHONY: all clean install uninstall
all: despotify 

# These are the files we depgen for. :-)
CFILES = $(DESPOTIFY_OBJS:.o=.c)
include ../depgen.mk

%.o: %.c
	@echo CC $<
	$(SILENT)$(CC) $(CFLAGS) -o $@ -c $<

despotify: $(DESPOTIFY_OBJS) $(LIB)
	@echo LD $@
	$(SILENT)$(LT) --mode=link $(CC) -o $@ $(CFLAGS) $(LDFLAGS) $(LIB) $(DESPOTIFY_OBJS)

clean:
	$(LT) --mode=clean rm -f despotify
	rm -f $(DESPOTIFY_OBJS) Makefile.dep

install: despotify 
	@echo "Copying despotify binary to $(INSTALL_PREFIX)/bin/despotify"
	$(LT) --mode=install install despotify $(INSTALL_PREFIX)/bin/despotify

uninstall:
	@echo "Removing despotify..."
	rm -f $(INSTALL_PREFIX)/bin/despotify
