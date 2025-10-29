package com.mr1aminul.civicmanagement;

public class Config {
    // Sync intervals in milliseconds
    public static final long SYNC_INTERVAL = 9000; // 30 seconds
    public static final long LOCATION_SYNC_INTERVAL = 300000; // 5 minutes

    // Feature toggles
    public static final boolean LOCATION_TRACKING_ENABLED = true;
    public static final boolean NOTIFICATIONS_ENABLED = true;
    public static final boolean ALARMS_ENABLED = true;
    public static final boolean CALL_TRACKING_ENABLED = true;

    // Server endpoints
    public static final String SERVER_BASE_URL = "https://civicgroupbd.com/app_api/";
    public static final String LOCATION_UPDATE_ENDPOINT = SERVER_BASE_URL + "location_update.php";

    /*
    * combined_data.php will get type `leads`, that's mean that notification will be go through floating notification or overlay
    * else other type will be goes as common notifications.
    * Also there has the alarms data inside the json.
    * */
    public static final String COMBINED_DATA_ENDPOINT = SERVER_BASE_URL + "combined_data.php";
    public static final String ALARMS_ENDPOINT = SERVER_BASE_URL + "alarms.php";

    // Default URL
    public static final String DEFAULT_URL = "https://civicgroupbd.com/management/";
}
