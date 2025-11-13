We are working now on /manage/pages/clients/*
it's xhr endpoint file are /xhr/manage_clients.php and /xhr/manage_inventory.php

also note this Wo_Ajax_Requests_File(), this are provides the endpoint base link, so no need to redefine the function
<script>
function Wo_Ajax_Requests_File(){
    return "<?php echo $wo['config']['site_url'].'/requests.php';?>"
}
</script>

also note this, we are working with raw php, no framework, with mysql database, the database's dump are already given in `/Database Structure Dump/civicbd_group___database_dump.sql`