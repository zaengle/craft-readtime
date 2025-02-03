# Read Time Field

Read Time creates a new Field Type called Read Time. When added to an entry, the field will loop through all of the content fields and calculate the estimated read time of the text content and save the value for use on the front end. The Read Time field will update every time the Entry is saved.

## Settings

The Words per Minute value used to determine read time can be changed in the plugin settings. The default is set to 200 words per minute.

## How to Use

Create a new field with the Read Time field type and add it to your page Entry Type. The Read Time value will automatically update when the Entry is saved. If you save content within a CKEditor longform entry block, you will need to also save the parent entry to update the Read Time value.

To display the field on the front end, call the field in the template by the field handle and apply the filter of the desired display:

### Human

This is the default display type. It will automatically format the time in hours, minutes, and seconds. If hours or minutes do not apply, they will be excluded from the display.

```twig
{{ entry.fieldHandle }}
{{ entry.fieldHandle.human }} 
```

Example: 211 seconds will display as `3 minutes and 31 seconds`. 

### Simple

Same as the Human display but the seconds will be excluded. The minutes will round to the nearest minute. 

```twig
{{ entry.fieldHandle.simple }} 
```

Example: 211 seconds will display as `4 minutes`. 

### DateTime

Outputs the Read Time value as a date/time value that can be formatted.

```twig
{{ entry.fieldHandle.dateInterval|date('%h hours, %i minutes, %s seconds') }} 
```

Example: 211 seconds will display as `0 hours, 3 minutes, 31 seconds`. 

## Content Updates

If the field is added to a section after content is added, the content will need to be resaved to fill the Read Time field value. To resave a whole section at once, use the CLI command:

`craft resave/entries --section sectionHandle`

If you have a lot of entries and want to add it to the Craft queue, add the `--queue` flag to the command.

## Compatibility

The Read Time plugin will work on both Craft 4 and Craft 5. Currently, there is only support for Craft native fields and CKEditor. 

## Read Time Roadmap

- [ ] Tests
- [ ] Update Longform logic when CKEditor v5.0 is released

Brought to you by [Zaengle Corp](https://zaengle.com/)