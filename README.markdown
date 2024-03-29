Slurpetta
=========

A command line PHP script that downloads and updates a copy of the latest stable
version of:

* Every plugin in the [WordPress.org plugin directory](https://wordpress.org/plugins/) with at least 10,000 active installations
* Every theme in the [WordPress.org theme directory](https://wordpress.org/themes/) with at least 1,000 active installations
* WordPress core latest release and latest nightly

As of March 2024 this is **around 2,500 plugins** and **700** themes.

Slurping and updating just these plugins and themes is at least 20x faster and smaller than
slurping the entire plugin and theme repos which would otherwise total over 100,000 items.

Really handy for doing local searches across popular WordPress plugins, themes, and core.

Requirements
------------

* Unix system (tested on Mac OS X and Linux)
* PHP 5.2 or higher
* `wget` and `svn` command-line executables installed

Instructions
------------

Run this from within the `slurpetta` directory:

```sh
./update plugins
./update themes
./update core
```

The `plugins` and `themes` directories will contain all the plugins and themes when the script is done.
The `core` directory will contain the latest release of WordPress core.

### Scanning the repo

You'll likely have the best experience using [ripgrep](https://github.com/BurntSushi/ripgrep) to search for files. It's available via package managers for macOS, Linux, and Windows, and it's just about the fastest tool available for regex searching across a large number of files.

Examples:

```
rg --type php 'rest_get_date_with_gmt' plugins
```

This repository also includes a script to show a summary of a scan.  For example:

```sh
rg --type php 'rest_get_date_with_gmt' plugins | tee scans/rest_get_date_with_gmt.txt
./summarize-scan.php scans/rest_get_date_with_gmt.txt
```

```
5 matching plugins
Matches  Plugin                             Active installs
=======  ======                             ===============
      4  rest-api                                   40,000+
      4  wptoandroid                                    30+
      5  custom-contact-forms                       60,000+
      2  appmaker-wp-mobile-app-manager                 50+
      4  appmaker-woocommerce-mobile-app-manager       200+

5 matching themes
Matches  Theme                              Active installs
=======  =====                              ===============
      4  rest-api                                   40,000+
      4  wptoandroid                                    30+
      5  custom-contact-forms                       60,000+
      2  appmaker-wp-mobile-app-manager                 50+
      4  appmaker-woocommerce-mobile-app-manager       200+
```

FAQ
----

### Why download the zip files? Why not use SVN?

An SVN checkout of the entire repository is a BEAST of a thing. You don't want it,
trust me. Updates and cleanups can take **hours** or even **days** to complete.

### How long will it take?

Your first update will take a while but depends entirely on your connection and
disk speeds. On a fast modern machine with a fast internet connection it may take
as little as 10 minutes, but be prepared for it to take hours on a machine with
a slower connection or disk speeds.

But subsequent updates are smarter. The script tracks the SVN revision numbers
of your latest updates and then asks the SVN repositories for a list of plugins
and themes that have changed since. Only those changed are updated after the
initial sync.

### How much disk space do I need?

As of March 2024:

* Around 12 GB of disk space for plugins
* Around 3 GB of disk space for themes
* A few MB of disk space for WordPress core

### Something went wrong, how do I do a partial update?

The last successful update revision numbers are stored in `plugins/.last-revision`.
and `themes/.last-revision`. You can just overwrite one of those and the next `update`
will start after that revision.

### What is this thing actually doing to my computer?

Once downloads have started, you can use a command like this to monitor the
tasks being executed by this tool:

```sh
watch -n .5 "pstree -pa `pgrep -f '^xargs -n 1 -P .+ ./download'`"
```

Many thanks
-----------

This is an adaptation of [the WordPress Plugin Directory Slurper](https://github.com/markjaquith/WordPress-Plugin-Directory-Slurper) by Mark Jaquith. The majority of the code was written by Mark and the other contributors to that library. If you need the entire plugin directory rather than just those with at least 10,000 active installations, then use that instead.

Copyright & License
-------------------
Copyright (C) 2011 Mark Jaquith, 2024 John Blackbourn

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
