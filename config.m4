PHP_ARG_ENABLE([fast-uuid],
  [whether to enable fast_uuid support],
  [AS_HELP_STRING([--enable-fast-uuid],
    [Enable fast_uuid support])],
  [no])

PHP_ARG_WITH([libuuid-dir],
  [libuuid (util-linux) install prefix],
  [AS_HELP_STRING([--with-libuuid-dir=DIR],
    [fast_uuid: path to libuuid prefix; used for v1 time-based UUIDs])],
  [no], [no])

if test "$PHP_FAST_UUID" != "no"; then

  dnl --- locate libuuid (optional: only used to back uuid1/uuid6) ---
  AC_MSG_CHECKING([for libuuid headers])
  LIBUUID_DIR=""
  for i in $PHP_LIBUUID_DIR /usr/local /usr; do
    if test -r "$i/include/uuid/uuid.h"; then
      LIBUUID_DIR=$i
      break
    fi
  done

  if test -n "$LIBUUID_DIR"; then
    AC_MSG_RESULT([found in $LIBUUID_DIR])
    PHP_ADD_INCLUDE($LIBUUID_DIR/include)
    PHP_CHECK_LIBRARY(uuid, uuid_generate_time_safe, [
      PHP_ADD_LIBRARY_WITH_PATH(uuid, $LIBUUID_DIR/$PHP_LIBDIR, FAST_UUID_SHARED_LIBADD)
      AC_DEFINE(HAVE_LIBUUID, 1, [Whether libuuid is available])
    ], [
      AC_MSG_WARN([libuuid present but unusable; using internal v1 generator])
    ], [
      -L$LIBUUID_DIR/$PHP_LIBDIR
    ])
  else
    AC_MSG_RESULT([not found; using internal v1 generator])
  fi

  PHP_SUBST(FAST_UUID_SHARED_LIBADD)
  AC_DEFINE(HAVE_FAST_UUID, 1, [fast_uuid enabled])
  PHP_NEW_EXTENSION(fast_uuid, fast_uuid.c, $ext_shared,, [-DZEND_ENABLE_STATIC_TSRMLS_CACHE=1])
fi
