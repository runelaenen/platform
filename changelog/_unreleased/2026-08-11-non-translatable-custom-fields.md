---
title: Non-translatable custom fields
author: Rune Laenen
author_github: @runelaenen
---
# Core
* Added `translatable` field to `Shopware\Core\System\CustomField\CustomFieldDefinition`, defaults to `true`
* Added `Shopware\Core\System\CustomField\DataAbstractionLayer\NonTranslatableCustomFieldRerouter` which writes values of non-translatable custom fields to the system default language
* Added `Shopware\Core\System\CustomField\Cleanup\CleanupNonTranslatableCustomFieldHandler` and the `custom-field:cleanup-non-translatable` console command
* Added optional `translatable` element to the custom field XML schema for apps and plugins
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor` and `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslationsAssociationFieldSerializer` to reroute writes of non-translatable custom fields
___
# Administration
* Added a `Translatable` switch to the custom field settings, including a confirmation before per language values are deleted
* Added a shared value indicator to `sw-custom-field-set-renderer` and removed the per language override for non-translatable custom fields
___
# Upgrade Information
## Custom fields can be marked as not translatable

Custom fields are translatable by default: every language holds its own value. A custom field can now be
marked as not translatable, which means it holds a single value that is shared across all languages. This is
useful for values that are not language specific, for example a boolean that enables a feature or an
identifier from an external system.

### Writing non-translatable custom fields

Values of a non-translatable custom field are always stored in the system default language translation,
regardless of which language the write is addressed to. This applies to every write that goes through the
data abstraction layer, including the Admin API, the Sync API, imports and writes that address a translation
entity such as `product_translation` directly. Writes are not rejected and no error is returned.

If a single payload writes the same custom field for more than one language, the existing precedence of the
data abstraction layer applies: an explicit `translations` entry wins over the shorthand notation, and among
explicit `translations` entries the last one in the payload wins.

Writing `null` for a non-translatable custom field is rerouted like any other value and therefore clears the
shared value for all languages. Clearing the whole `customFields` object is not rerouted and only clears the
addressed translation.

### Making an existing custom field non-translatable deletes data

When an existing custom field is switched to non-translatable, the values that were entered for other
languages are deleted by a background task, so they cannot shadow the shared value. Records that only had a
value in another language end up without a value. The Administration asks for confirmation before this
happens, but a change through the Admin API or through an app or plugin update triggers the same deletion
without an interactive warning.

The cleanup runs through the message queue, so it can take a moment until the values are removed everywhere.
It verifies the flag before every batch, which means switching the custom field back to translatable stops a
pending cleanup. Switching back does not restore already deleted values. The cleanup can be repeated at any
time:

```
bin/console custom-field:cleanup-non-translatable [<custom-field-name>]
```

### Declaring the flag in apps and plugins

The custom field XML schema accepts an optional `translatable` element:

```xml
<bool name="my_shared_flag">
    <label>My shared flag</label>
    <translatable>false</translatable>
</bool>
```

The element is only written when it is present, so updating an app or plugin without the element keeps the
value that is currently stored instead of resetting it to the default.

### Reading is unchanged

No read behaviour changed. The translation chain already resolves a value that only exists in the system
default language for every language, so the shared value is returned by the Admin API, the Store API and the
storefront without any adjustment.
