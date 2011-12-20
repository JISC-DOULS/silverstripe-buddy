<%-- List of searches saved by user --%>

<% if searches %>
<div id="BuddySearches">
<table summary="List of saved searches" class="buddySearchTable">
<thead>
<tr>
<th scope="col"><% _t('BUDDY.buddysearchname', 'Name') %></th>
<th scope="col"><% _t('BUDDY.buddysearchcreated', 'Created') %></th>
<th scope="col"><% _t('BUDDY.buddysearchedit', 'Last ran') %></th>
<th scope="col"><% _t('BUDDY.buddysearchrun', 'Run') %></th>
<th scope="col"><% _t('BUDDY.buddysearchdel', 'Delete') %></th>
</tr>
</thead>
<tbody>
<% control searches %>
<tr>
<td>$Name.XML</td>
<td>$Created.Nice</td>
<td>$LastEdited.Ago</td>
<td><a href="$Top.Link/find/$ID" title="<% _t('BUDDY.buddysearchrun', 'Run') %> $Name"><% _t('BUDDY.buddysearchrun', 'Run this search') %></a></td>
<td>
<%-- delete - can't send params to form, so must make semi-manually in template --%>
        <% control Top.deleteForm %>
        <form $FormAttributes >
        <div class="delete_actions">
        <% control Actions %>
            $Field
        <% end_control %>
        </div>
        <div>
        <% control Fields %>
            $Field
        <% end_control %>
        <% end_control %>
        <input type='hidden' name='id' value="$ID"/></div>
        </form>
</td>
</tr>
<% end_control %>
</tbody>
</table>
</div>
<% end_if %>