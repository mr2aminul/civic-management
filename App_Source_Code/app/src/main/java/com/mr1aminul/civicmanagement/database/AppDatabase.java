package com.mr1aminul.civicmanagement.database;

import android.content.Context;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.util.Log;

/**
 * SQLite database helper for managing app data locally
 * Handles location history, notifications, alarms, call logs, and app usage tracking
 */
public class AppDatabase extends SQLiteOpenHelper {
    private static final String TAG = "AppDatabase";
    private static final String DATABASE_NAME = "civic_management.db";
    private static final int DATABASE_VERSION = 1;
    
    // Table names
    public static final String TABLE_LOCATION_HISTORY = "location_history";
    public static final String TABLE_NOTIFICATIONS = "notifications";
    public static final String TABLE_ALARMS = "alarms";
    public static final String TABLE_CALL_LOGS = "call_logs";
    public static final String TABLE_APP_USAGE = "app_usage";
    public static final String TABLE_LEADS = "leads";
    public static final String TABLE_SYNC_STATUS = "sync_status";
    
    // Location history columns
    public static final String COL_ID = "id";
    public static final String COL_USER_ID = "user_id";
    public static final String COL_LATITUDE = "latitude";
    public static final String COL_LONGITUDE = "longitude";
    public static final String COL_ACCURACY = "accuracy";
    public static final String COL_TIMESTAMP = "timestamp";
    public static final String COL_SYNCED = "synced";
    
    // Notification columns
    public static final String COL_NOTIF_ID = "notif_id";
    public static final String COL_TITLE = "title";
    public static final String COL_DESCRIPTION = "description";
    public static final String COL_TYPE = "type";
    public static final String COL_URL = "url";
    public static final String COL_READ = "read";
    
    // Alarm columns
    public static final String COL_ALARM_ID = "alarm_id";
    public static final String COL_TRIGGER_TIME = "trigger_time";
    public static final String COL_STATUS = "status";
    
    // Call log columns
    public static final String COL_PHONE_NUMBER = "phone_number";
    public static final String COL_CALL_TYPE = "call_type";
    public static final String COL_DURATION = "duration";
    public static final String COL_CONTACT_NAME = "contact_name";
    
    // App usage columns
    public static final String COL_PACKAGE_NAME = "package_name";
    public static final String COL_APP_NAME = "app_name";
    public static final String COL_USAGE_TIME = "usage_time";
    public static final String COL_LAST_USED = "last_used";
    
    // Lead columns
    public static final String COL_LEAD_ID = "lead_id";
    public static final String COL_LEAD_NAME = "lead_name";
    public static final String COL_LEAD_PHONE = "lead_phone";
    public static final String COL_LEAD_STATUS = "lead_status";
    public static final String COL_LAST_FOLLOWUP = "last_followup";
    
    // Sync status columns
    public static final String COL_TABLE_NAME = "table_name";
    public static final String COL_LAST_SYNC = "last_sync";
    
    public AppDatabase(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
    }
    
    @Override
    public void onCreate(SQLiteDatabase db) {
        Log.d(TAG, "Creating database tables");
        
        // Location history table
        db.execSQL("CREATE TABLE " + TABLE_LOCATION_HISTORY + " (" +
                COL_ID + " INTEGER PRIMARY KEY AUTOINCREMENT," +
                COL_USER_ID + " TEXT NOT NULL," +
                COL_LATITUDE + " REAL NOT NULL," +
                COL_LONGITUDE + " REAL NOT NULL," +
                COL_ACCURACY + " REAL," +
                COL_TIMESTAMP + " LONG NOT NULL," +
                COL_SYNCED + " INTEGER DEFAULT 0" +
                ")");
        
        // Notifications table
        db.execSQL("CREATE TABLE " + TABLE_NOTIFICATIONS + " (" +
                COL_ID + " INTEGER PRIMARY KEY AUTOINCREMENT," +
                COL_NOTIF_ID + " TEXT UNIQUE NOT NULL," +
                COL_USER_ID + " TEXT NOT NULL," +
                COL_TITLE + " TEXT NOT NULL," +
                COL_DESCRIPTION + " TEXT," +
                COL_TYPE + " TEXT DEFAULT 'normal'," +
                COL_URL + " TEXT," +
                COL_READ + " INTEGER DEFAULT 0," +
                COL_TIMESTAMP + " LONG NOT NULL" +
                ")");
        
        // Alarms table
        db.execSQL("CREATE TABLE " + TABLE_ALARMS + " (" +
                COL_ID + " INTEGER PRIMARY KEY AUTOINCREMENT," +
                COL_ALARM_ID + " TEXT UNIQUE NOT NULL," +
                COL_USER_ID + " TEXT NOT NULL," +
                COL_TITLE + " TEXT NOT NULL," +
                COL_DESCRIPTION + " TEXT," +
                COL_TRIGGER_TIME + " LONG NOT NULL," +
                COL_STATUS + " TEXT DEFAULT 'active'," +
                COL_URL + " TEXT," +
                COL_TIMESTAMP + " LONG NOT NULL" +
                ")");
        
        // Call logs table
        db.execSQL("CREATE TABLE " + TABLE_CALL_LOGS + " (" +
                COL_ID + " INTEGER PRIMARY KEY AUTOINCREMENT," +
                COL_USER_ID + " TEXT NOT NULL," +
                COL_PHONE_NUMBER + " TEXT NOT NULL," +
                COL_CALL_TYPE + " INTEGER," +
                COL_DURATION + " LONG," +
                COL_CONTACT_NAME + " TEXT," +
                COL_TIMESTAMP + " LONG NOT NULL," +
                COL_SYNCED + " INTEGER DEFAULT 0" +
                ")");
        
        // App usage table
        db.execSQL("CREATE TABLE " + TABLE_APP_USAGE + " (" +
                COL_ID + " INTEGER PRIMARY KEY AUTOINCREMENT," +
                COL_USER_ID + " TEXT NOT NULL," +
                COL_PACKAGE_NAME + " TEXT NOT NULL," +
                COL_APP_NAME + " TEXT," +
                COL_USAGE_TIME + " LONG DEFAULT 0," +
                COL_LAST_USED + " LONG," +
                COL_TIMESTAMP + " LONG NOT NULL," +
                COL_SYNCED + " INTEGER DEFAULT 0" +
                ")");
        
        // Leads table
        db.execSQL("CREATE TABLE " + TABLE_LEADS + " (" +
                COL_ID + " INTEGER PRIMARY KEY AUTOINCREMENT," +
                COL_LEAD_ID + " TEXT UNIQUE NOT NULL," +
                COL_USER_ID + " TEXT NOT NULL," +
                COL_LEAD_NAME + " TEXT NOT NULL," +
                COL_LEAD_PHONE + " TEXT," +
                COL_LEAD_STATUS + " TEXT," +
                COL_LAST_FOLLOWUP + " LONG," +
                COL_TIMESTAMP + " LONG NOT NULL" +
                ")");
        
        // Sync status table
        db.execSQL("CREATE TABLE " + TABLE_SYNC_STATUS + " (" +
                COL_ID + " INTEGER PRIMARY KEY AUTOINCREMENT," +
                COL_TABLE_NAME + " TEXT UNIQUE NOT NULL," +
                COL_LAST_SYNC + " LONG DEFAULT 0" +
                ")");
        
        // Create indexes for better query performance
        db.execSQL("CREATE INDEX idx_location_user_timestamp ON " + TABLE_LOCATION_HISTORY + 
                "(" + COL_USER_ID + ", " + COL_TIMESTAMP + ")");
        db.execSQL("CREATE INDEX idx_notifications_user ON " + TABLE_NOTIFICATIONS + 
                "(" + COL_USER_ID + ")");
        db.execSQL("CREATE INDEX idx_call_logs_user ON " + TABLE_CALL_LOGS + 
                "(" + COL_USER_ID + ", " + COL_TIMESTAMP + ")");
        db.execSQL("CREATE INDEX idx_app_usage_user ON " + TABLE_APP_USAGE + 
                "(" + COL_USER_ID + ", " + COL_PACKAGE_NAME + ")");
        
        Log.d(TAG, "Database tables created successfully");
    }
    
    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        Log.d(TAG, "Upgrading database from version " + oldVersion + " to " + newVersion);
        // Handle future database upgrades here
    }
}
