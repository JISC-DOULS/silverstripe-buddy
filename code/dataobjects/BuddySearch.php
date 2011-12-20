<?php
/**
 *Data Object containing saved search options
 */

class BuddySearch extends DataObject {
    public static $db = array(
        'Name' => 'VarChar(25)',
        'Options' => 'Text',
    );

    public static $has_one = array(
        'Initiator' => 'Member',
    );

    public static $defaults = array(
        'Name' => 'No name'
    );

    public static $summary_fields = array(
        'InitiatorID',
        'InitiatorName',
        'Name'
    );

    /**
     * To stop errors in CMS searchable fields must be set
     * so custom functions added to summary fields are ignored
     */
    public static $searchable_fields = array(
        'InitiatorID',
    );

    /**
     * In CMS summary fields show name of member
     */
    public function getInitiatorName() {
        return $this->getComponent('Initiator')->getName();
    }

    /**
     * Returns all searchs for member
     * @param int $memberid
     * @return DataObjectSet
     */
    public static function get_searches(int $memberid) {
        return DataObject::get('BuddySearch', "InitiatorID = $memberid", 'Created DESC');
    }

    /**
     * Deletes a record from this table
     * @param int $id
     */
    public static function delete_search(int $id) {
        return DataObject::delete_by_id('BuddySearch', $id);
    }

    /**
     * Checks a search exists and belongs to specified user
     * @param int $id
     * @param int $memberid
     * @return boolean
     */
    public static function check_search_belongs(int $id, int $memberid) {
        $rec = DataObject::get_by_id('BuddySearch', $id);
        if ($rec && $rec->InitiatorID == $memberid) {
            return true;
        } else {
            return false;
        }
    }
}
