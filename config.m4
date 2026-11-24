PHP_ARG_ENABLE([fast-uuid],
  [whether to enable fast_uuid support],
  [AS_HELP_STRING([--enable-fast-uuid],
    [Enable fast_uuid support])],
  [no])

if test "$PHP_FAST_UUID" != "no"; then
  AC_DEFINE(HAVE_FAST_UUID, 1, [fast_uuid enabled])
  FAST_UUID_CFLAGS="-DZEND_ENABLE_STATIC_TSRMLS_CACHE=1"
  if test "$ext_shared" = "yes"; then
    AC_MSG_CHECKING([whether compiler supports -fvisibility=hidden])
    fu_saved_CFLAGS="$CFLAGS"
    CFLAGS="$CFLAGS -fvisibility=hidden"
    AC_COMPILE_IFELSE([AC_LANG_PROGRAM([], [])], [
      AC_MSG_RESULT([yes])
      FAST_UUID_CFLAGS="$FAST_UUID_CFLAGS -fvisibility=hidden"
    ], [
      AC_MSG_RESULT([no])
    ])
    CFLAGS="$fu_saved_CFLAGS"
  fi
  PHP_NEW_EXTENSION(fast_uuid, fast_uuid.c, $ext_shared,, $FAST_UUID_CFLAGS)
fi
