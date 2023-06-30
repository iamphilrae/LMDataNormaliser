# Living Map POI Data Normaliser

Reads POI information from a Living Map dataset, normalises it, then returns data as a new dataset.

## What is this Built In?

This utility is built as a PHP CLI application on top of the [Laravel](https://laravel.com) framework. It makes heavy use of the [Artisan Console](https://laravel.com/docs/10.x/artisan#main-content) functionality.

## Usage

### Normalise a single data field

```bash
# Normalise a dataset from a single field ('primary_key', 'output' and 'report' are optional)
php artisan app:normalise-data schema table field 
  --primary_key=id \
  --output=./storage/output/%schema%.%table%.%field%;normalised.json \
  --report=./storage/output/%schema%.%table%.%field%;report.csv
```

### Normalise a batch of fields

The following file contains a list of `scheme.table.field` combinations which a batch normalisation process will take place on:

`/storage/app/data/fields_to_batch_normalise.json`

Running the following will perform the batch normalisation over that file:

```bash
# Batch normalise a dataset from a multiple fields
php artisan app:normalise-data-batch
```



## What is Normalised?

* HTML formatting tags are stripped.
* HTML `<style>` and `<script>` tags are stripped.
* HTML `<img>` tags are stripped and flagged for manual inspection.
* HTML lists (`<ul>`/`<ol>`/`<dl>`) are flagged for manual inspection.
* Line breaks (`<br>`) are converted to `\n`.
* Paragraphs and dual line-breaks (`<br><br>`) are converted to `\n\n`.
