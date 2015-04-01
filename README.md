OctoberFriends Recommendations
==============================

A pluging for Friends platform for give Activities and Badges recommendations using ElasticSearch as engine.

# Requirements
* PHP >= 5.4
* ElasticSearch >= 1.4.0

# Installation

* Download and complete the installation for October CMS (http://octobercms.com)
* install DMA.friends plugin
* Extract this repository into plugins/dma/recommendations
* In plugins/dma/recommendations folder run composer install.
* Enable the "Friends Recommendations" Plugin
* Go to Recommendation engine settings under Friends settings section and enable which feature per Item the engine should use. 

# Theme

The Recommendations plugin is fully themeable through the use of partials. To change the output format for recommendations, create a directory 'recommendations' in your theme's partials directory and place the necessary partial files in there. It's recommended that you leave the default partial alone and customize only the activitylist.htm and badgelist.htm files, but you can customize default.htm if you have a need to.

If you are also customizing activity lists from the DMA Friends plugin (for instance, the lists created by ActivityCatalog component), you can create a single set of partials that will apply to both the Recommendations plugin and the Friends ActivityCatalog component by simply aliasing your components when referencing them in your theme pages files. For instance on one page you could use:

```
[ActivityCatalog 'activitylist']
==
{% component 'activitylist' %}
```

...and on another page use:

```
[Recommendations 'activitylist']
==
{% component 'activitylist' %}
```

Then in your theme's partials directory, just create the single directory, 'activitylist', and put the activitylist.htm partial in there. Both components will use the same template for outputting lists of activities and you can DRY up your theme. You cannot use this technique if you are using both the Recommendations component and the ActivityCatalog component on the same page, but hopefully the use cases for that scenario are few.

At the moment, the Recommendations list cannot be filtered or sorted using the controls provided in the Friends ActivityFilters component, but work is underway to make that possible.