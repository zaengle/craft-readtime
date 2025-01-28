# Read Time Field

Read Time creates a new Field Type called Read Time. When added to an entry, the field will loop through all of the content fields and calculate the estimated read time of the text content and save the value for use on the front end. The Read Time field will update every time the Entry is saved.

## Settings

The Words per Minute value used to determine read time can be changed in the plugin settings. The default is set to 200 words per minute.

## How to Use

Create a new field with the Read Time field type and add it to your page Entry Type. The Read Time value will automatically update when the Entry is saved. If you save content within a CKEditor longform entry block, you will need to also save the parent entry to update the Read Time value.

To display the field on the front end, call the field in the template by the field handle and apply the filter of the desired display:

### Seconds (Integer)

This is the default value. Example: 211 seconds will display as `211`

```twig
  {{ entry.fieldHandle }} 
  {{ entry.fieldHandle|inSeconds }} 
```

### Minutes (Integer)

Example: 211 seconds will display as `3`

```twig
  {{ entry.fieldHandle|inMinutes }} 
```

### Hours (Integer)

Example: 211 seconds will display as `0`. 

```twig
  {{ entry.fieldHandle|inHours }}
```

### Human (String)

Example: 211 seconds will display as `3 minutes and 31 seconds`. 

```twig
  {{ entry.fieldHandle|human }}
```

### Simple (String)

Seconds will be excluded and the minutes will round to the nearest minute. Example: 211 seconds will display as `4 minutes`. 

```twig
  {{ entry.fieldHandle|simple }}
```

## Compatibility

The Read Time plugin will work on both Craft 4 and Craft 5. Currently, there is only support for Craft native fields and CKEditor. 

## Read Time Roadmap

- [ ] Tests
- [ ] Update Longform logic when CKEditor v5.0 is released

Brought to you by [Zaengle Corp](https://zaengle.com/)