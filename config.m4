PHP_ARG_ENABLE([fast-uuid],
  [whether to enable fast_uuid support],
  [AS_HELP_STRING([--enable-fast-uuid],
    [Enable fast_uuid support])],
  [no])

if test "$PHP_FAST_UUID" != "no"; then
  AC_DEFINE(HAVE_FAST_UUID, 1, [fast_uuid enabled])
  PHP_NEW_EXTENSION(fast_uuid, fast_uuid.c, $ext_shared,, [-DZEND_ENABLE_STATIC_TSRMLS_CACHE=1])
fi
