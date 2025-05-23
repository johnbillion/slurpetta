# Slurpetta

A command line PHP script that downloads and updates a copy of the latest stable
version of:

* Every plugin in the WordPress.org directory with at least 10,000 active installations
* Every theme in the WordPress.org directory with at least 1,000 active installations
* WordPress core latest and nightly

As of May 2025 this is **around 2,400 plugins** and **600** themes.

Slurping and updating just these plugins and themes is at least 20x faster and smaller than
slurping the entire plugin and theme repos which would otherwise total over 100,000 items.

Really handy for doing local searches across popular WordPress plugins, themes, and core.

## Requirements

* Unix system (tested on macOS and Linux)
* PHP 8.0 or higher
* `wget` and `svn` command-line executables installed

## Instructions

Run this from within the `slurpetta` directory:

```sh
./update plugins
./update themes
./update core
```

When the script is done:

* The `plugins` and `themes` directories contain all the plugins and themes
* The `popular` directory contains symlinks to all plugins with over 1M active installations
* The `top` directory contains symlinks to all plugins with over 5M active installations
* The `core` directory contains the latest release of WordPress core

## Scanning the results

### Simple scanning

You'll likely have the best experience using [ripgrep](https://github.com/BurntSushi/ripgrep) to search for files. It's available via package managers for macOS, Linux, and Windows, and it's just about the fastest tool available for regex searching across a large number of files.

Examples:

```sh
rg --type php 'rest_get_date_with_gmt' plugins
```

### Finding files

```sh
find plugins -name 'foo.php'
```

### Advanced scanning

It's possible to perform more powerful searches that are aware of language syntax and semantics using [Semgrep](https://github.com/semgrep/semgrep). It's available via package managers or via Docker. You don't need to sign into the Semgrep Code service on the CLI despite what its documentation says.

Semgrep allows you to perform searches using [its language-aware pattern syntax](https://semgrep.dev/docs/writing-rules/pattern-syntax/). Benefits include ignoring code comments and being aware of multi-line matches because it's aware of the semantics of the code beyond simple static analysis. Note that complex searches will take a lot more time than standard searches with ripgrep.

Examples:

```sh
semgrep -e 'printf(esc_attr__(...), ...)' --lang=php --no-git-ignore plugins
```

There is a built-in ruleset for PHP that you can use, but running it across all plugins will give you a very large number of results so you may want to restrict it to a sub-directory or a single plugin.

```sh
semgrep --config "p/php" --no-git-ignore plugins/a
semgrep --config "p/php" --no-git-ignore plugins/a/akismet
```

There is also a built-in ruleset specifically for vulnerabilities in WordPress code. You can scan all the plugins with this rule because at the time of writing it only shows around 50 results.

```sh
semgrep --config "p/wordpress" --no-git-ignore plugins
```

### Generating scan summaries

This repository also includes a script to show a summary of a scan.  For example:

```sh
rg --type php 'rest_get_date_with_gmt' plugins themes | tee scans/rest_get_date_with_gmt.txt
./summarize-scan.php scans/rest_get_date_with_gmt.txt
```

```
Matching plugins: 83
Matches  Slug                               Active installs
=======  ====                               ===============
     11  woocommerce                             5,000,000+
      1  mailchimp-for-wp                        2,000,000+
      1  advanced-custom-fields                  2,000,000+
     52  custom-post-type-ui                     1,000,000+
      5  astra-sites                             1,000,000+
      1  better-wp-security                        900,000+
      1  woocommerce-gateway-stripe                800,000+
      8  imagify                                   800,000+
      1  woocommerce-payments                      700,000+
      1  premium-addons-for-elementor              700,000+

Matching themes: 0

Matching core: 2
Matches  Slug  Active installs
=======  ====  ===============
      5  latest              -
      5  nightly             -
```

## FAQ

### What can I use this for?

* Scanning (SAST)
* Producing stats
* Training an LLM

### Why download the zip files? Why not use SVN?

An SVN checkout of the entire repository is a BEAST of a thing. You don't want it,
trust me. Updates and cleanups can take **hours** or even **days** to complete.

### How long will it take?

Your first update will take a while but depends entirely on your connection and
disk speeds. On a fast modern machine with a fast internet connection it may take
as little as 15 minutes, but be prepared for it to take hours on a machine with
a slower connection or disk speeds.

But subsequent updates are smarter. The script tracks the SVN revision numbers
of your latest updates and then asks the SVN repositories for a list of plugins
and themes that have changed since. Only those changed are updated after the
initial sync.

### How much disk space do I need?

As of May 2025:

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

## Many thanks

This is an adaptation of [the WordPress Plugin Directory Slurper](https://github.com/markjaquith/WordPress-Plugin-Directory-Slurper) by Mark Jaquith. The majority of the code was originally written by Mark and the other contributors to that library. If you need the entire plugin directory rather than just those with at least 10,000 active installations, then use that instead.

## Copyright & License

Copyright © 2011-2020 Mark Jaquith, 2024-2025 John Blackbourn

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
