CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration

INTRODUCTION
------------

The Flickr formatter module provides a field formatter for
images retrieved from FLickr via Flickr API contributed module.
It contains logic for "pluggable" sub-modules,
such as "Flickr formatter Bootstrap" sub-module provided. 

REQUIREMENTS
------------

This module requires the following themes/modules:

 * Flickr API (https://www.drupal.org/project/flickr_api)
 * Bootstrap Theme (https://www.drupal.org/project/bootstrap_barrio)

INSTALLATION
------------

 * Install the Flickr formatter as you would normally install a
   contributed Drupal module. Visit https://www.drupal.org/node/1897420 for
   further information.
 * Optionally install Flickr formatter Bootstrap sub-module for some of
   available Bootstrap components for Photosets.

CONFIGURATION
-------------

    1. Go to "Configuration > Media > Flickr API Settings"
       and enter your Flickr credentials, API key and Secret.
    2. Define new or use existing standard Text plain or Number field for
       your content type. Flickr ID will be entered as a value of this field.
    3. On "Manage Display" tab for the content type choose "Flickr"
       as a formatter. Then click gear icon to set its settings and styles.
