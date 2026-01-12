# IfCastle package installer [![PHP Composer](https://github.com/EdmondDantes/ifcastle-package-installer/actions/workflows/php.yml/badge.svg)](https://github.com/EdmondDantes/ifcastle-package-installer/actions/workflows/php.yml)

This Composer plugin allows automatic configuration of `Bootloader` and `ServiceManager` for the 
`IFCastle` framework.

## Installation

To install the package, run the following command:

```bash
composer require ifcastle/package-installer
```

## Usage

The package uses the description from `composer.json` to configure 
the application's `BootloaderManager`. 
The package **adds**, **updates**, or **removes** a package from the `Bootloader` zone.

To use the package, add the following configuration to the `composer.json` file:

```json
{
  "extra": {
    "ifcastle-installer": {
      "package": {
        "name": "configurator",
        "isActive": true,
        "mainConfig": {
          "section1": {
            "comment": "This is a comment\nThe section of the main configuration file",
            "config": {
              "key1": "value1",
              "key2": "value2"
            }
          },
          "section2": {
              "config": {
              "key3": "value3",
              "key4": "value4"
              }
          }
        },
        "runtimeTags": ["tag1", "tag2"],
        "excludeTags": ["tag3", "tag4"],
        "bootloaders": [
          "IfCastle\\Configurator\\ConfigApplication"
        ],
        "applications": [
          "console",
          "web"
        ]
      }
    }
  }
}
```

or 

```json
{
  "extra": {
    "ifcastle-installer": {
      "package": {
        "name": "configurator",
        "groups": [
          {
            "isActive": true,
            "bootloaders": ["list of classes"],
            "applications": ["application1", "application2"],
            "runtimeTags": ["tag1", "tag2"],
            "excludeTags": ["tag3", "tag4"],
            "group": "configurator"
          }
        ]
      }
    }
  }
}
```

* `ifcastle-installer` - Name of an installer section.
* `package` - Main package section.
* `name` - Name of the package.
* `mainConfig` - Main configuration section.
* `bootloaders` - List of bootloader classes which will be added to the `Bootloader` zone.
* `applications` - A list of strings, tags that indicate the type of application 
for which the specified `Bootloader` will be applied.
* `groups` - A list of groups that contain the following fields:
  * `isActive` - A boolean value that indicates whether the group is active.
  * `bootloaders` - List of bootloader classes which will be added to the `Bootloader` zone.
  * `applications` - A list of strings, tags that indicate the type of application 
  for which the specified `Bootloader` will be applied.
  * `runtimeTags` - A list of tags that must be defined at the application's startup for the Bootloader 
  to include the specified classes in the loading stage.
  * `excludeTags` - A list of tags that must not be defined at the application's startup for the Bootloader
  * `group` - A string that indicates the group name.

## Main configuration

The application has a main configuration file, typically named `main.ini` or `main.toml`.
Using the mainConfig section, you can define default configuration blocks 
that will be added to the file by the installer if they are not already defined.

Example:
```json
 "mainConfig": {
          "section1": {
            "comment": "This is a comment\nThe section of the main configuration file",
            "config": {
              "key1": "value1",
              "key2": "value2"
            }
          }
```

Next keys are available:
- `section` - A string that indicates the section name.
- `comment` - A string that will be added as a comment to the configuration block.
- `config` - An array of key-value pairs that will be added to the configuration block.

## Install services

Component may contain services that need to be registered with the ServiceManager. 
In this case, use the following configuration in the composer.json file.

```json
{
  "extra": {
    "ifcastle-installer": {
      "services": [
        {
          "name": "service1",
          "isActive": true,
          "class": "IfCastle\\Configurator\\Service1",
          "config": {
            "key1": "value1",
            "key2": "value2"
          },
          "tags": ["tag1", "tag2"],
          "excludeTags": ["tag3", "tag4"]
        }
      ]
    }
  }
}
```

Nodes:

* `services` - A list of services that contain the following fields:
* `name` - Name of the service.
* `isActive` - A boolean value that indicates whether the service is active.
* `class` - Class name of the service.