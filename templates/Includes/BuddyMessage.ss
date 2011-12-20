<%-- Error/Warning Messages --%>
<% if Messages %>
<div id="BuddyMessages">
<% control Messages %>
<p class="message $type">$text</p>
<% end_control %>
</div>
<% end_if %>