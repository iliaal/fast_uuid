/* This is a generated file, edit fast_uuid.stub.php instead.
 * Stub hash: 48fc3227a12c69a771da8ea56710f2a28d3cd26a */

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_uuid_v1, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

#define arginfo_uuid_v1_bin arginfo_uuid_v1

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_uuid_v3, 0, 2, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, ns, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
ZEND_END_ARG_INFO()

#define arginfo_uuid_v3_bin arginfo_uuid_v3

#define arginfo_uuid_v4 arginfo_uuid_v1

#define arginfo_uuid_v4_bin arginfo_uuid_v1

#define arginfo_uuid_v4_fast arginfo_uuid_v1

#define arginfo_uuid_v4_fast_bin arginfo_uuid_v1

#define arginfo_uuid_v5 arginfo_uuid_v3

#define arginfo_uuid_v5_bin arginfo_uuid_v3

#define arginfo_uuid_v6 arginfo_uuid_v1

#define arginfo_uuid_v6_bin arginfo_uuid_v1

#define arginfo_uuid_v7 arginfo_uuid_v1

#define arginfo_uuid_v7_bin arginfo_uuid_v1

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_uuid_v7_at, 0, 1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, unixMillis, IS_LONG, 0)
ZEND_END_ARG_INFO()

#define arginfo_uuid_v7_at_bin arginfo_uuid_v7_at

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_uuid_v8, 0, 1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, bytes, IS_STRING, 0)
ZEND_END_ARG_INFO()

#define arginfo_uuid_v8_bin arginfo_uuid_v8

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_uuid_v4_batch, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, count, IS_LONG, 0)
ZEND_END_ARG_INFO()

#define arginfo_uuid_v7_batch arginfo_uuid_v4_batch

#define arginfo_uuid_v4_bin_batch arginfo_uuid_v4_batch

#define arginfo_uuid_v7_bin_batch arginfo_uuid_v4_batch

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_uuid_to_bin, 0, 1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, uuid, IS_STRING, 0)
ZEND_END_ARG_INFO()

#define arginfo_uuid_from_bin arginfo_uuid_v8

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_uuid_is_valid, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, uuid, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_fast_uuid_random_bytes, 0, 1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, length, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_FastUuid_Uuid_uuid1, 0, 0, FastUuid\\\125uidInterface, 0)
	ZEND_ARG_TYPE_MASK(0, node, MAY_BE_LONG|MAY_BE_STRING|MAY_BE_NULL, "null")
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, clockSeq, IS_LONG, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_FastUuid_Uuid_uuid2, 0, 1, FastUuid\\\125uidInterface, 0)
	ZEND_ARG_TYPE_INFO(0, localDomain, IS_LONG, 0)
	ZEND_ARG_TYPE_MASK(0, localIdentifier, MAY_BE_LONG|MAY_BE_STRING|MAY_BE_NULL, "null")
	ZEND_ARG_TYPE_MASK(0, node, MAY_BE_LONG|MAY_BE_STRING|MAY_BE_NULL, "null")
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, clockSeq, IS_LONG, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_FastUuid_Uuid_uuid3, 0, 2, FastUuid\\\125uidInterface, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, ns, FastUuid\\\125uidInterface, MAY_BE_STRING, NULL)
	ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_FastUuid_Uuid_uuid4, 0, 0, FastUuid\\\125uidInterface, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_FastUuid_Uuid_uuid5 arginfo_class_FastUuid_Uuid_uuid3

#define arginfo_class_FastUuid_Uuid_uuid6 arginfo_class_FastUuid_Uuid_uuid1

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_FastUuid_Uuid_uuid7, 0, 0, FastUuid\\\125uidInterface, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, dateTime, DateTimeInterface, MAY_BE_LONG|MAY_BE_NULL, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_FastUuid_Uuid_uuid8, 0, 1, FastUuid\\\125uidInterface, 0)
	ZEND_ARG_TYPE_INFO(0, bytes, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_FastUuid_Uuid_fromString, 0, 1, FastUuid\\\125uidInterface, 0)
	ZEND_ARG_TYPE_INFO(0, uuid, IS_STRING, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_FastUuid_Uuid_fromBytes arginfo_class_FastUuid_Uuid_uuid8

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_FastUuid_Uuid_fromInteger, 0, 1, FastUuid\\\125uidInterface, 0)
	ZEND_ARG_TYPE_INFO(0, integer, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_FastUuid_Uuid_fromHexadecimal, 0, 1, FastUuid\\\125uidInterface, 0)
	ZEND_ARG_OBJ_TYPE_MASK(0, hex, Stringable, MAY_BE_STRING, NULL)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_FastUuid_Uuid_fromDateTime, 0, 1, FastUuid\\\125uidInterface, 0)
	ZEND_ARG_OBJ_INFO(0, dateTime, DateTimeInterface, 0)
	ZEND_ARG_TYPE_MASK(0, node, MAY_BE_LONG|MAY_BE_STRING|MAY_BE_NULL, "null")
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, clockSeq, IS_LONG, 1, "null")
ZEND_END_ARG_INFO()

#define arginfo_class_FastUuid_Uuid_isValid arginfo_uuid_is_valid

ZEND_BEGIN_ARG_INFO_EX(arginfo_class_FastUuid_Uuid___construct, 0, 0, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_FastUuid_Uuid_toString arginfo_uuid_v1

#define arginfo_class_FastUuid_Uuid___toString arginfo_uuid_v1

#define arginfo_class_FastUuid_Uuid_getBytes arginfo_uuid_v1

#define arginfo_class_FastUuid_Uuid_toBytes arginfo_uuid_v1

#define arginfo_class_FastUuid_Uuid_getHex arginfo_uuid_v1

#define arginfo_class_FastUuid_Uuid_toHexadecimal arginfo_uuid_v1

#define arginfo_class_FastUuid_Uuid_getUrn arginfo_uuid_v1

#define arginfo_class_FastUuid_Uuid_toUrn arginfo_uuid_v1

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_FastUuid_Uuid_getVersion, 0, 0, IS_LONG, 1)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_FastUuid_Uuid_getVariant, 0, 0, IS_LONG, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_FastUuid_Uuid_getInteger arginfo_uuid_v1

#define arginfo_class_FastUuid_Uuid_toInteger arginfo_uuid_v1

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_FastUuid_Uuid_getDateTime, 0, 0, DateTimeImmutable, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_FastUuid_Uuid_getTimestampMillis arginfo_class_FastUuid_Uuid_getVariant

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_FastUuid_Uuid_getFields, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_FastUuid_Uuid_equals, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, other, IS_MIXED, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_FastUuid_Uuid_compareTo, 0, 1, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, other, IS_MIXED, 0)
ZEND_END_ARG_INFO()

#define arginfo_class_FastUuid_Uuid_jsonSerialize arginfo_uuid_v1

#define arginfo_class_FastUuid_Uuid___serialize arginfo_class_FastUuid_Uuid_getFields

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_FastUuid_Uuid___unserialize, 0, 1, IS_VOID, 0)
	ZEND_ARG_TYPE_INFO(0, data, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_FastUuid_Uuid___set_state, 0, 1, IS_STATIC, 0)
	ZEND_ARG_TYPE_INFO(0, an_array, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

ZEND_FUNCTION(uuid_v1);
ZEND_FUNCTION(uuid_v1_bin);
ZEND_FUNCTION(uuid_v3);
ZEND_FUNCTION(uuid_v3_bin);
ZEND_FUNCTION(uuid_v4);
ZEND_FUNCTION(uuid_v4_bin);
ZEND_FUNCTION(uuid_v4_fast);
ZEND_FUNCTION(uuid_v4_fast_bin);
ZEND_FUNCTION(uuid_v5);
ZEND_FUNCTION(uuid_v5_bin);
ZEND_FUNCTION(uuid_v6);
ZEND_FUNCTION(uuid_v6_bin);
ZEND_FUNCTION(uuid_v7);
ZEND_FUNCTION(uuid_v7_bin);
ZEND_FUNCTION(uuid_v7_at);
ZEND_FUNCTION(uuid_v7_at_bin);
ZEND_FUNCTION(uuid_v8);
ZEND_FUNCTION(uuid_v8_bin);
ZEND_FUNCTION(uuid_v4_batch);
ZEND_FUNCTION(uuid_v7_batch);
ZEND_FUNCTION(uuid_v4_bin_batch);
ZEND_FUNCTION(uuid_v7_bin_batch);
ZEND_FUNCTION(uuid_to_bin);
ZEND_FUNCTION(uuid_from_bin);
ZEND_FUNCTION(uuid_is_valid);
ZEND_FUNCTION(fast_uuid_random_bytes);
ZEND_METHOD(FastUuid_Uuid, uuid1);
ZEND_METHOD(FastUuid_Uuid, uuid2);
ZEND_METHOD(FastUuid_Uuid, uuid3);
ZEND_METHOD(FastUuid_Uuid, uuid4);
ZEND_METHOD(FastUuid_Uuid, uuid5);
ZEND_METHOD(FastUuid_Uuid, uuid6);
ZEND_METHOD(FastUuid_Uuid, uuid7);
ZEND_METHOD(FastUuid_Uuid, uuid8);
ZEND_METHOD(FastUuid_Uuid, fromString);
ZEND_METHOD(FastUuid_Uuid, fromBytes);
ZEND_METHOD(FastUuid_Uuid, fromInteger);
ZEND_METHOD(FastUuid_Uuid, fromHexadecimal);
ZEND_METHOD(FastUuid_Uuid, fromDateTime);
ZEND_METHOD(FastUuid_Uuid, isValid);
ZEND_METHOD(FastUuid_Uuid, __construct);
ZEND_METHOD(FastUuid_Uuid, toString);
ZEND_METHOD(FastUuid_Uuid, getBytes);
ZEND_METHOD(FastUuid_Uuid, getHex);
ZEND_METHOD(FastUuid_Uuid, getUrn);
ZEND_METHOD(FastUuid_Uuid, getVersion);
ZEND_METHOD(FastUuid_Uuid, getVariant);
ZEND_METHOD(FastUuid_Uuid, getInteger);
ZEND_METHOD(FastUuid_Uuid, getDateTime);
ZEND_METHOD(FastUuid_Uuid, getTimestampMillis);
ZEND_METHOD(FastUuid_Uuid, getFields);
ZEND_METHOD(FastUuid_Uuid, equals);
ZEND_METHOD(FastUuid_Uuid, compareTo);
ZEND_METHOD(FastUuid_Uuid, jsonSerialize);
ZEND_METHOD(FastUuid_Uuid, __serialize);
ZEND_METHOD(FastUuid_Uuid, __unserialize);
ZEND_METHOD(FastUuid_Uuid, __set_state);

static const zend_function_entry ext_functions[] = {
	ZEND_FE(uuid_v1, arginfo_uuid_v1)
	ZEND_FE(uuid_v1_bin, arginfo_uuid_v1_bin)
	ZEND_FE(uuid_v3, arginfo_uuid_v3)
	ZEND_FE(uuid_v3_bin, arginfo_uuid_v3_bin)
	ZEND_FE(uuid_v4, arginfo_uuid_v4)
	ZEND_FE(uuid_v4_bin, arginfo_uuid_v4_bin)
	ZEND_FE(uuid_v4_fast, arginfo_uuid_v4_fast)
	ZEND_FE(uuid_v4_fast_bin, arginfo_uuid_v4_fast_bin)
	ZEND_FE(uuid_v5, arginfo_uuid_v5)
	ZEND_FE(uuid_v5_bin, arginfo_uuid_v5_bin)
	ZEND_FE(uuid_v6, arginfo_uuid_v6)
	ZEND_FE(uuid_v6_bin, arginfo_uuid_v6_bin)
	ZEND_FE(uuid_v7, arginfo_uuid_v7)
	ZEND_FE(uuid_v7_bin, arginfo_uuid_v7_bin)
	ZEND_FE(uuid_v7_at, arginfo_uuid_v7_at)
	ZEND_FE(uuid_v7_at_bin, arginfo_uuid_v7_at_bin)
	ZEND_FE(uuid_v8, arginfo_uuid_v8)
	ZEND_FE(uuid_v8_bin, arginfo_uuid_v8_bin)
	ZEND_FE(uuid_v4_batch, arginfo_uuid_v4_batch)
	ZEND_FE(uuid_v7_batch, arginfo_uuid_v7_batch)
	ZEND_FE(uuid_v4_bin_batch, arginfo_uuid_v4_bin_batch)
	ZEND_FE(uuid_v7_bin_batch, arginfo_uuid_v7_bin_batch)
	ZEND_FE(uuid_to_bin, arginfo_uuid_to_bin)
	ZEND_FE(uuid_from_bin, arginfo_uuid_from_bin)
	ZEND_FE(uuid_is_valid, arginfo_uuid_is_valid)
	ZEND_FE(fast_uuid_random_bytes, arginfo_fast_uuid_random_bytes)
	ZEND_FE_END
};

static const zend_function_entry class_FastUuid_Uuid_methods[] = {
	ZEND_ME(FastUuid_Uuid, uuid1, arginfo_class_FastUuid_Uuid_uuid1, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, uuid2, arginfo_class_FastUuid_Uuid_uuid2, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, uuid3, arginfo_class_FastUuid_Uuid_uuid3, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, uuid4, arginfo_class_FastUuid_Uuid_uuid4, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, uuid5, arginfo_class_FastUuid_Uuid_uuid5, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, uuid6, arginfo_class_FastUuid_Uuid_uuid6, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, uuid7, arginfo_class_FastUuid_Uuid_uuid7, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, uuid8, arginfo_class_FastUuid_Uuid_uuid8, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, fromString, arginfo_class_FastUuid_Uuid_fromString, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, fromBytes, arginfo_class_FastUuid_Uuid_fromBytes, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, fromInteger, arginfo_class_FastUuid_Uuid_fromInteger, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, fromHexadecimal, arginfo_class_FastUuid_Uuid_fromHexadecimal, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, fromDateTime, arginfo_class_FastUuid_Uuid_fromDateTime, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, isValid, arginfo_class_FastUuid_Uuid_isValid, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(FastUuid_Uuid, __construct, arginfo_class_FastUuid_Uuid___construct, ZEND_ACC_PRIVATE)
	ZEND_ME(FastUuid_Uuid, toString, arginfo_class_FastUuid_Uuid_toString, ZEND_ACC_PUBLIC)
	ZEND_RAW_FENTRY("__toString", zim_FastUuid_Uuid_toString, arginfo_class_FastUuid_Uuid___toString, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(FastUuid_Uuid, getBytes, arginfo_class_FastUuid_Uuid_getBytes, ZEND_ACC_PUBLIC)
	ZEND_RAW_FENTRY("toBytes", zim_FastUuid_Uuid_getBytes, arginfo_class_FastUuid_Uuid_toBytes, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(FastUuid_Uuid, getHex, arginfo_class_FastUuid_Uuid_getHex, ZEND_ACC_PUBLIC)
	ZEND_RAW_FENTRY("toHexadecimal", zim_FastUuid_Uuid_getHex, arginfo_class_FastUuid_Uuid_toHexadecimal, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(FastUuid_Uuid, getUrn, arginfo_class_FastUuid_Uuid_getUrn, ZEND_ACC_PUBLIC)
	ZEND_RAW_FENTRY("toUrn", zim_FastUuid_Uuid_getUrn, arginfo_class_FastUuid_Uuid_toUrn, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(FastUuid_Uuid, getVersion, arginfo_class_FastUuid_Uuid_getVersion, ZEND_ACC_PUBLIC)
	ZEND_ME(FastUuid_Uuid, getVariant, arginfo_class_FastUuid_Uuid_getVariant, ZEND_ACC_PUBLIC)
	ZEND_ME(FastUuid_Uuid, getInteger, arginfo_class_FastUuid_Uuid_getInteger, ZEND_ACC_PUBLIC)
	ZEND_RAW_FENTRY("toInteger", zim_FastUuid_Uuid_getInteger, arginfo_class_FastUuid_Uuid_toInteger, ZEND_ACC_PUBLIC, NULL, NULL)
	ZEND_ME(FastUuid_Uuid, getDateTime, arginfo_class_FastUuid_Uuid_getDateTime, ZEND_ACC_PUBLIC)
	ZEND_ME(FastUuid_Uuid, getTimestampMillis, arginfo_class_FastUuid_Uuid_getTimestampMillis, ZEND_ACC_PUBLIC)
	ZEND_ME(FastUuid_Uuid, getFields, arginfo_class_FastUuid_Uuid_getFields, ZEND_ACC_PUBLIC)
	ZEND_ME(FastUuid_Uuid, equals, arginfo_class_FastUuid_Uuid_equals, ZEND_ACC_PUBLIC)
	ZEND_ME(FastUuid_Uuid, compareTo, arginfo_class_FastUuid_Uuid_compareTo, ZEND_ACC_PUBLIC)
	ZEND_ME(FastUuid_Uuid, jsonSerialize, arginfo_class_FastUuid_Uuid_jsonSerialize, ZEND_ACC_PUBLIC)
	ZEND_ME(FastUuid_Uuid, __serialize, arginfo_class_FastUuid_Uuid___serialize, ZEND_ACC_PUBLIC)
	ZEND_ME(FastUuid_Uuid, __unserialize, arginfo_class_FastUuid_Uuid___unserialize, ZEND_ACC_PUBLIC)
	ZEND_ME(FastUuid_Uuid, __set_state, arginfo_class_FastUuid_Uuid___set_state, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_FE_END
};

static zend_class_entry *register_class_FastUuid_UuidInterface(zend_class_entry *class_entry_JsonSerializable, zend_class_entry *class_entry_Stringable)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "FastUuid", "UuidInterface", NULL);
	class_entry = zend_register_internal_interface(&ce);
	zend_class_implements(class_entry, 2, class_entry_JsonSerializable, class_entry_Stringable);

	return class_entry;
}

static zend_class_entry *register_class_FastUuid_Uuid(zend_class_entry *class_entry_FastUuid_UuidInterface)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "FastUuid", "Uuid", class_FastUuid_Uuid_methods);
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES);
	zend_class_implements(class_entry, 1, class_entry_FastUuid_UuidInterface);

	zval const_NIL_value;
	zend_string *const_NIL_value_str = zend_string_init("00000000-0000-0000-0000-000000000000", strlen("00000000-0000-0000-0000-000000000000"), 1);
	ZVAL_STR(&const_NIL_value, const_NIL_value_str);
	zend_string *const_NIL_name = zend_string_init_interned("NIL", sizeof("NIL") - 1, true);
	zend_declare_class_constant_ex(class_entry, const_NIL_name, &const_NIL_value, ZEND_ACC_PUBLIC, NULL);
	zend_string_release_ex(const_NIL_name, true);

	zval const_MAX_value;
	zend_string *const_MAX_value_str = zend_string_init("ffffffff-ffff-ffff-ffff-ffffffffffff", strlen("ffffffff-ffff-ffff-ffff-ffffffffffff"), 1);
	ZVAL_STR(&const_MAX_value, const_MAX_value_str);
	zend_string *const_MAX_name = zend_string_init_interned("MAX", sizeof("MAX") - 1, true);
	zend_declare_class_constant_ex(class_entry, const_MAX_name, &const_MAX_value, ZEND_ACC_PUBLIC, NULL);
	zend_string_release_ex(const_MAX_name, true);

	zval const_NAMESPACE_DNS_value;
	zend_string *const_NAMESPACE_DNS_value_str = zend_string_init("6ba7b810-9dad-11d1-80b4-00c04fd430c8", strlen("6ba7b810-9dad-11d1-80b4-00c04fd430c8"), 1);
	ZVAL_STR(&const_NAMESPACE_DNS_value, const_NAMESPACE_DNS_value_str);
	zend_string *const_NAMESPACE_DNS_name = zend_string_init_interned("NAMESPACE_DNS", sizeof("NAMESPACE_DNS") - 1, true);
	zend_declare_class_constant_ex(class_entry, const_NAMESPACE_DNS_name, &const_NAMESPACE_DNS_value, ZEND_ACC_PUBLIC, NULL);
	zend_string_release_ex(const_NAMESPACE_DNS_name, true);

	zval const_NAMESPACE_URL_value;
	zend_string *const_NAMESPACE_URL_value_str = zend_string_init("6ba7b811-9dad-11d1-80b4-00c04fd430c8", strlen("6ba7b811-9dad-11d1-80b4-00c04fd430c8"), 1);
	ZVAL_STR(&const_NAMESPACE_URL_value, const_NAMESPACE_URL_value_str);
	zend_string *const_NAMESPACE_URL_name = zend_string_init_interned("NAMESPACE_URL", sizeof("NAMESPACE_URL") - 1, true);
	zend_declare_class_constant_ex(class_entry, const_NAMESPACE_URL_name, &const_NAMESPACE_URL_value, ZEND_ACC_PUBLIC, NULL);
	zend_string_release_ex(const_NAMESPACE_URL_name, true);

	zval const_NAMESPACE_OID_value;
	zend_string *const_NAMESPACE_OID_value_str = zend_string_init("6ba7b812-9dad-11d1-80b4-00c04fd430c8", strlen("6ba7b812-9dad-11d1-80b4-00c04fd430c8"), 1);
	ZVAL_STR(&const_NAMESPACE_OID_value, const_NAMESPACE_OID_value_str);
	zend_string *const_NAMESPACE_OID_name = zend_string_init_interned("NAMESPACE_OID", sizeof("NAMESPACE_OID") - 1, true);
	zend_declare_class_constant_ex(class_entry, const_NAMESPACE_OID_name, &const_NAMESPACE_OID_value, ZEND_ACC_PUBLIC, NULL);
	zend_string_release_ex(const_NAMESPACE_OID_name, true);

	zval const_NAMESPACE_X500_value;
	zend_string *const_NAMESPACE_X500_value_str = zend_string_init("6ba7b814-9dad-11d1-80b4-00c04fd430c8", strlen("6ba7b814-9dad-11d1-80b4-00c04fd430c8"), 1);
	ZVAL_STR(&const_NAMESPACE_X500_value, const_NAMESPACE_X500_value_str);
	zend_string *const_NAMESPACE_X500_name = zend_string_init_interned("NAMESPACE_X500", sizeof("NAMESPACE_X500") - 1, true);
	zend_declare_class_constant_ex(class_entry, const_NAMESPACE_X500_name, &const_NAMESPACE_X500_value, ZEND_ACC_PUBLIC, NULL);
	zend_string_release_ex(const_NAMESPACE_X500_name, true);

	zval const_DCE_DOMAIN_PERSON_value;
	ZVAL_LONG(&const_DCE_DOMAIN_PERSON_value, 0);
	zend_string *const_DCE_DOMAIN_PERSON_name = zend_string_init_interned("DCE_DOMAIN_PERSON", sizeof("DCE_DOMAIN_PERSON") - 1, true);
	zend_declare_class_constant_ex(class_entry, const_DCE_DOMAIN_PERSON_name, &const_DCE_DOMAIN_PERSON_value, ZEND_ACC_PUBLIC, NULL);
	zend_string_release_ex(const_DCE_DOMAIN_PERSON_name, true);

	zval const_DCE_DOMAIN_GROUP_value;
	ZVAL_LONG(&const_DCE_DOMAIN_GROUP_value, 1);
	zend_string *const_DCE_DOMAIN_GROUP_name = zend_string_init_interned("DCE_DOMAIN_GROUP", sizeof("DCE_DOMAIN_GROUP") - 1, true);
	zend_declare_class_constant_ex(class_entry, const_DCE_DOMAIN_GROUP_name, &const_DCE_DOMAIN_GROUP_value, ZEND_ACC_PUBLIC, NULL);
	zend_string_release_ex(const_DCE_DOMAIN_GROUP_name, true);

	zval const_DCE_DOMAIN_ORG_value;
	ZVAL_LONG(&const_DCE_DOMAIN_ORG_value, 2);
	zend_string *const_DCE_DOMAIN_ORG_name = zend_string_init_interned("DCE_DOMAIN_ORG", sizeof("DCE_DOMAIN_ORG") - 1, true);
	zend_declare_class_constant_ex(class_entry, const_DCE_DOMAIN_ORG_name, &const_DCE_DOMAIN_ORG_value, ZEND_ACC_PUBLIC, NULL);
	zend_string_release_ex(const_DCE_DOMAIN_ORG_name, true);

	return class_entry;
}

static zend_class_entry *register_class_FastUuid_Exception_InvalidArgumentException(zend_class_entry *class_entry_InvalidArgumentException)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "FastUuid\\Exception", "InvalidArgumentException", NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_InvalidArgumentException, 0);

	return class_entry;
}

static zend_class_entry *register_class_FastUuid_Exception_InvalidUuidStringException(zend_class_entry *class_entry_FastUuid_Exception_InvalidArgumentException)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "FastUuid\\Exception", "InvalidUuidStringException", NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_FastUuid_Exception_InvalidArgumentException, 0);

	return class_entry;
}

static zend_class_entry *register_class_FastUuid_Exception_UnsupportedOperationException(zend_class_entry *class_entry_LogicException)
{
	zend_class_entry ce, *class_entry;

	INIT_NS_CLASS_ENTRY(ce, "FastUuid\\Exception", "UnsupportedOperationException", NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_LogicException, 0);

	return class_entry;
}
