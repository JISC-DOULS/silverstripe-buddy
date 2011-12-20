<p>This is a list of all of your buddies:
You have $Buddies.Count Buddies
</p>
<% if Buddies %>
<table summary="List of Buddies" class="buddyTable">
<thead>
<tr>
<th scope="col"><% _t('BUDDY.buddyavatar', 'Avatar') %></th>
<th scope="col"><% _t('BUDDY.buddyname', 'Name') %></th>
<th scope="col"><% _t('BUDDY.buddynewmessage', 'Send message') %></th>
<th scope="col"><% _t('BUDDY.unbuddy', 'Remove buddy') %></th>
</tr>
</thead>
<tbody>
<% control Buddies %>
    <tr>
    <!-- Work out who in the relationship is the Buddy (not the current member) -->
    <% if Buddy.isCurrentMember %>
        <% control Initiator %>
            <% include BuddyListRow %>
        <% end_control %>
    <% else %>
        <% control Buddy %>
            <% include BuddyListRow %>
        <% end_control %>
    <% end_if %>
    </tr>
<% end_control %>
</tbody>
</table>
<!-- PAGINATION -->
<% if Buddies.MoreThanOnePage %>
<div class="pagination" style="text-align:center">
  <p>
  <% if Buddies.PrevLink %>
    <a href="$Buddies.PrevLink">
      &laquo; <% _t('PREVIOUS', 'prev') %>
    </a> |
  <% else %>
    &laquo; <% _t('PREVIOUS', 'prev') %> |
  <% end_if %>

  <% control Buddies.PaginationSummary(5) %>
    <% if CurrentBool %>
      <strong>$PageNum</strong>
    <% else %>
      <a href="$Link" title="<% _t('GOTOPAGE', 'Go to page') %> $PageNum">
        $PageNum
      </a>
    <% end_if %>
  <% end_control %>

  <% if Buddies.NextLink %>
    | <a href="$Buddies.NextLink">
      <% _t('NEXT', 'next') %> &raquo;
    </a>
  <% else %>
    | <% _t('NEXT', 'next') %> &raquo;
  <% end_if %>
  </p>
</div>
<% end_if %>
<!-- END PAGINATION -->
<% end_if %>