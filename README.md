Asynchronous Reference Indexing for TYPO3
=========================================

> Delegates reference index updating to an asynchronous queue, processed by CLI / scheduler

What does it do?
----------------

Provides a couple of things:

* An override class for DataHandler which replaces a single method, `updateRefIndex`, causing
  on-the-fly indexing to be skipped, instead delegating to a queue.
* A similar override for the ReferenceIndex class which replaces methods called also outside
  of DataHandler, to catch those cases.
* An SQL table storing queued reference index updates.
* A CommandController which can be executed via CLI to process queued reference indexing
  without running into timeout or long wait issues.
  
Depending on how often your editors perform record imports, copies, deletions etc. this can over
time save many, many hours of waiting for the TYPO3 backend to respond.

For further information about the performance aspects see the "Background" section below.

Installing
----------

Only available through Packagist (or via GitHub). Installation via Composer is recommended:

```
composer require namelesscoder/asynchronous-reference-indexing
```

Then enable the extension in TYPO3. This can be done with a CLI command:

```
./typo3/cli_dispatch.phpsh extbase extension:install asynchronous_reference_indexing
```

Word of warning
---------------

Failing to update the reference index can have negative effects on your site in some cases, both
in frontend and backend. You are advised to add a scheduler task or cronjob for the included
command controller *and set the frequency to a very low value such as once every minute*. The
controller maintains a lock file and prevents parallel executions, so frequent runs are safe.

Possible side effects
---------------------

Delaying update of the reference index has one main side effect: if the editor tries to delete a
record whose relations have not been indexed, an appropriate warning may not be shown.

Secondary side effect is in listing of relationships between records. Such information will be
updated only when the command controller runs.

Frontend rendering should not be affected negatively.

Background
----------

This community extension exists for one reason alone: *increasing responsiveness of the TYPO3
backend when performing record operations*. Due to the internal structure of the ReferenceIndex
class in TYPO3, any record operation which might potentially change references causes an extreme
amount of SQL traffic.

At the time of writing this (2016-12-04) the problem can be illustrated as follows:

* Assume you use `sys_category` relations for 10,000 different records (from any table to one `sys_category`)
* Updating, importing, deleting or copying any record pointing to this `sys_category` triggers index update
* Index update itself cascades to process *all 10,000 `sys_category` records for every record you edited*
* Depending on the number of records you edited this may cause hundreds of thousands of SQL requests

A significant effort has already been made to improve the reference indexing performance, however,
all improvements are inevitably minor without a complete rewrite of the entire reference indexing
logic. Again, at the time of writing this, the ReferenceIndex and RelationHandler classes are
mutually dependent and will recursively call each other, thus further compounding the performance
problem described above. Since these technical challenges are very hard to overcome, this extension
is presented as a temporary solution to increase responsiveness of record operations in TYPO3 by
upwards of 90% reduction in wall time.

You read that right. **90%** - nine-zero percent.

Credit
------

This work and all preceding investigation was sponsored by [Systime](https://systime.dk/).
Systime is a Danish online publishing firm specialising in "i-books" for the educational market,
and they faced severe problems with reference indexing in particular.

References
----------

* https://forge.typo3.org/issues/78629
* https://review.typo3.org/#/c/50559

(performance profiles not linked as they are not permanently online)
