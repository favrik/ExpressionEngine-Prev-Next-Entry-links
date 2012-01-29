favrik_nextprev
===============

What is it?
-----------
A somewhat simple ExpressionEngine plugin to display Prev/Next Entry links according to the `id` parameter value. The `id` value can be numeric (an entry id) or a string (think url_title).

Usage Example
----------

    {exp:favrik_nextprev:prev_entry id="{segment_5}"}
        <a href="{site_url}{channel_name}/{entry_date format="%Y/%m/%d"}/{url_title}">Prev: {title}</a>
    {/exp:favrik_nextprev:prev_entry}

    {exp:favrik_nextprev:next_entry id="{segment_5}"}
        <a href="{site_url}{channel_short_nane}/{entry_date format="%Y/%m/%d"}/{url_title}">{title}</a>
    {/exp:favrik_nextprev:next_entry}



Changelog
---------
###1.0.1 - Bug fixes and improvements
> * Add validation for when the `id` passed does not exist in the database
> * Return FALSE when no rows are found
> * By default don't show future entries
> * Update plugin URL


###1.0.0 - Initial Relase
