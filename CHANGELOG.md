# Changelog

## Release Version 1.3.1

### Allocate Providers

Before running the new migrations, make sure to execute the following command:

```bash
php bin/console reset:allocate-providers
```

This command is necessary to copy data from one place to another before the new migrations are applied. The new
migrations remove unnecessary fields from the database, so it's important to ensure the data is allocated properly to
avoid data loss during the migration process.

---

## Release Version 1.4

### Clear Event Command

This command is required for older versions that cannot run the new migrations. The `clear:eventEntity` command removes
any records in the `Event` entity that have empty or null fields.

To use this command, run the following in the root folder of the project:

```bash
php bin/console clear:eventEntity
```

> **Important**: This command will permanently delete any log or record in the `Event` entity that has an empty field.

For more details on how this command works, please refer to the file at:
**src/Command/ClearEventCommand.php**
