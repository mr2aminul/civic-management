package com.mr1aminul.civicmanagement.database;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.util.Log;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * Database manager for CRUD operations on app data
 * Provides methods for storing and retrieving location, notifications, alarms, and usage data
 */
public class DatabaseManager {
    private static final String TAG = "DatabaseManager";
    private static DatabaseManager instance;
    private AppDatabase dbHelper;
    private SQLiteDatabase db;
    
    private DatabaseManager(Context context) {
        dbHelper = new AppDatabase(context);
        db = dbHelper.getWritableDatabase();
    }
    
    public static synchronized DatabaseManager getInstance(Context context) {
        if (instance == null) {
            instance = new DatabaseManager(context.getApplicationContext());
        }
        return instance;
    }
    
    // Location history methods
    public long insertLocation(String userId, double latitude, double longitude, float accuracy) {
        try {
            ContentValues values = new ContentValues();
            values.put(AppDatabase.COL_USER_ID, userId);
            values.put(AppDatabase.COL_LATITUDE, latitude);
            values.put(AppDatabase.COL_LONGITUDE, longitude);
            values.put(AppDatabase.COL_ACCURACY, accuracy);
            values.put(AppDatabase.COL_TIMESTAMP, System.currentTimeMillis());
            values.put(AppDatabase.COL_SYNCED, 0);
            
            long result = db.insert(AppDatabase.TABLE_LOCATION_HISTORY, null, values);
            Log.d(TAG, "Location inserted: " + result);
            return result;
        } catch (Exception e) {
            Log.e(TAG, "Error inserting location", e);
            return -1;
        }
    }
    
    public List<Map<String, Object>> getUnsyncedLocations(String userId, int limit) {
        List<Map<String, Object>> locations = new ArrayList<>();
        try {
            Cursor cursor = db.query(
                    AppDatabase.TABLE_LOCATION_HISTORY,
                    null,
                    AppDatabase.COL_USER_ID + " = ? AND " + AppDatabase.COL_SYNCED + " = 0",
                    new String[]{userId},
                    null,
                    null,
                    AppDatabase.COL_TIMESTAMP + " DESC",
                    String.valueOf(limit)
            );
            
            while (cursor.moveToNext()) {
                Map<String, Object> location = new HashMap<>();
                location.put("id", cursor.getLong(cursor.getColumnIndex(AppDatabase.COL_ID)));
                location.put("latitude", cursor.getDouble(cursor.getColumnIndex(AppDatabase.COL_LATITUDE)));
                location.put("longitude", cursor.getDouble(cursor.getColumnIndex(AppDatabase.COL_LONGITUDE)));
                location.put("accuracy", cursor.getFloat(cursor.getColumnIndex(AppDatabase.COL_ACCURACY)));
                location.put("timestamp", cursor.getLong(cursor.getColumnIndex(AppDatabase.COL_TIMESTAMP)));
                locations.add(location);
            }
            cursor.close();
        } catch (Exception e) {
            Log.e(TAG, "Error getting unsynced locations", e);
        }
        return locations;
    }
    
    public void markLocationsSynced(List<Long> locationIds) {
        try {
            for (Long id : locationIds) {
                ContentValues values = new ContentValues();
                values.put(AppDatabase.COL_SYNCED, 1);
                db.update(AppDatabase.TABLE_LOCATION_HISTORY, values, 
                        AppDatabase.COL_ID + " = ?", new String[]{String.valueOf(id)});
            }
            Log.d(TAG, "Marked " + locationIds.size() + " locations as synced");
        } catch (Exception e) {
            Log.e(TAG, "Error marking locations as synced", e);
        }
    }
    
    // Notification methods
    public long insertNotification(String notifId, String userId, String title, String description, 
                                   String type, String url) {
        try {
            ContentValues values = new ContentValues();
            values.put(AppDatabase.COL_NOTIF_ID, notifId);
            values.put(AppDatabase.COL_USER_ID, userId);
            values.put(AppDatabase.COL_TITLE, title);
            values.put(AppDatabase.COL_DESCRIPTION, description);
            values.put(AppDatabase.COL_TYPE, type);
            values.put(AppDatabase.COL_URL, url);
            values.put(AppDatabase.COL_READ, 0);
            values.put(AppDatabase.COL_TIMESTAMP, System.currentTimeMillis());
            
            long result = db.insert(AppDatabase.TABLE_NOTIFICATIONS, null, values);
            Log.d(TAG, "Notification inserted: " + notifId);
            return result;
        } catch (Exception e) {
            Log.e(TAG, "Error inserting notification", e);
            return -1;
        }
    }
    
    public List<Map<String, Object>> getNotifications(String userId, int limit) {
        List<Map<String, Object>> notifications = new ArrayList<>();
        try {
            Cursor cursor = db.query(
                    AppDatabase.TABLE_NOTIFICATIONS,
                    null,
                    AppDatabase.COL_USER_ID + " = ?",
                    new String[]{userId},
                    null,
                    null,
                    AppDatabase.COL_TIMESTAMP + " DESC",
                    String.valueOf(limit)
            );
            
            while (cursor.moveToNext()) {
                Map<String, Object> notif = new HashMap<>();
                notif.put("id", cursor.getLong(cursor.getColumnIndex(AppDatabase.COL_ID)));
                notif.put("notif_id", cursor.getString(cursor.getColumnIndex(AppDatabase.COL_NOTIF_ID)));
                notif.put("title", cursor.getString(cursor.getColumnIndex(AppDatabase.COL_TITLE)));
                notif.put("description", cursor.getString(cursor.getColumnIndex(AppDatabase.COL_DESCRIPTION)));
                notif.put("type", cursor.getString(cursor.getColumnIndex(AppDatabase.COL_TYPE)));
                notif.put("url", cursor.getString(cursor.getColumnIndex(AppDatabase.COL_URL)));
                notif.put("read", cursor.getInt(cursor.getColumnIndex(AppDatabase.COL_READ)));
                notif.put("timestamp", cursor.getLong(cursor.getColumnIndex(AppDatabase.COL_TIMESTAMP)));
                notifications.add(notif);
            }
            cursor.close();
        } catch (Exception e) {
            Log.e(TAG, "Error getting notifications", e);
        }
        return notifications;
    }
    
    // Call log methods
    public long insertCallLog(String userId, String phoneNumber, int callType, long duration, String contactName) {
        try {
            ContentValues values = new ContentValues();
            values.put(AppDatabase.COL_USER_ID, userId);
            values.put(AppDatabase.COL_PHONE_NUMBER, phoneNumber);
            values.put(AppDatabase.COL_CALL_TYPE, callType);
            values.put(AppDatabase.COL_DURATION, duration);
            values.put(AppDatabase.COL_CONTACT_NAME, contactName);
            values.put(AppDatabase.COL_TIMESTAMP, System.currentTimeMillis());
            values.put(AppDatabase.COL_SYNCED, 0);
            
            long result = db.insert(AppDatabase.TABLE_CALL_LOGS, null, values);
            Log.d(TAG, "Call log inserted: " + phoneNumber);
            return result;
        } catch (Exception e) {
            Log.e(TAG, "Error inserting call log", e);
            return -1;
        }
    }
    
    public List<Map<String, Object>> getUnsyncedCallLogs(String userId, int limit) {
        List<Map<String, Object>> callLogs = new ArrayList<>();
        try {
            Cursor cursor = db.query(
                    AppDatabase.TABLE_CALL_LOGS,
                    null,
                    AppDatabase.COL_USER_ID + " = ? AND " + AppDatabase.COL_SYNCED + " = 0",
                    new String[]{userId},
                    null,
                    null,
                    AppDatabase.COL_TIMESTAMP + " DESC",
                    String.valueOf(limit)
            );
            
            while (cursor.moveToNext()) {
                Map<String, Object> log = new HashMap<>();
                log.put("id", cursor.getLong(cursor.getColumnIndex(AppDatabase.COL_ID)));
                log.put("phone_number", cursor.getString(cursor.getColumnIndex(AppDatabase.COL_PHONE_NUMBER)));
                log.put("call_type", cursor.getInt(cursor.getColumnIndex(AppDatabase.COL_CALL_TYPE)));
                log.put("duration", cursor.getLong(cursor.getColumnIndex(AppDatabase.COL_DURATION)));
                log.put("contact_name", cursor.getString(cursor.getColumnIndex(AppDatabase.COL_CONTACT_NAME)));
                log.put("timestamp", cursor.getLong(cursor.getColumnIndex(AppDatabase.COL_TIMESTAMP)));
                callLogs.add(log);
            }
            cursor.close();
        } catch (Exception e) {
            Log.e(TAG, "Error getting unsynced call logs", e);
        }
        return callLogs;
    }
    
    // App usage methods
    public long insertOrUpdateAppUsage(String userId, String packageName, String appName, long usageTime) {
        try {
            Cursor cursor = db.query(
                    AppDatabase.TABLE_APP_USAGE,
                    null,
                    AppDatabase.COL_USER_ID + " = ? AND " + AppDatabase.COL_PACKAGE_NAME + " = ?",
                    new String[]{userId, packageName},
                    null,
                    null,
                    null
            );
            
            long result;
            if (cursor.moveToFirst()) {
                long existingUsage = cursor.getLong(cursor.getColumnIndex(AppDatabase.COL_USAGE_TIME));
                ContentValues values = new ContentValues();
                values.put(AppDatabase.COL_USAGE_TIME, existingUsage + usageTime);
                values.put(AppDatabase.COL_LAST_USED, System.currentTimeMillis());
                result = db.update(AppDatabase.TABLE_APP_USAGE, values,
                        AppDatabase.COL_USER_ID + " = ? AND " + AppDatabase.COL_PACKAGE_NAME + " = ?",
                        new String[]{userId, packageName});
            } else {
                ContentValues values = new ContentValues();
                values.put(AppDatabase.COL_USER_ID, userId);
                values.put(AppDatabase.COL_PACKAGE_NAME, packageName);
                values.put(AppDatabase.COL_APP_NAME, appName);
                values.put(AppDatabase.COL_USAGE_TIME, usageTime);
                values.put(AppDatabase.COL_LAST_USED, System.currentTimeMillis());
                values.put(AppDatabase.COL_TIMESTAMP, System.currentTimeMillis());
                values.put(AppDatabase.COL_SYNCED, 0);
                result = db.insert(AppDatabase.TABLE_APP_USAGE, null, values);
            }
            cursor.close();
            return result;
        } catch (Exception e) {
            Log.e(TAG, "Error inserting/updating app usage", e);
            return -1;
        }
    }
    
    public List<Map<String, Object>> getAppUsageStats(String userId) {
        List<Map<String, Object>> stats = new ArrayList<>();
        try {
            Cursor cursor = db.query(
                    AppDatabase.TABLE_APP_USAGE,
                    null,
                    AppDatabase.COL_USER_ID + " = ?",
                    new String[]{userId},
                    null,
                    null,
                    AppDatabase.COL_USAGE_TIME + " DESC"
            );
            
            while (cursor.moveToNext()) {
                Map<String, Object> stat = new HashMap<>();
                stat.put("package_name", cursor.getString(cursor.getColumnIndex(AppDatabase.COL_PACKAGE_NAME)));
                stat.put("app_name", cursor.getString(cursor.getColumnIndex(AppDatabase.COL_APP_NAME)));
                stat.put("usage_time", cursor.getLong(cursor.getColumnIndex(AppDatabase.COL_USAGE_TIME)));
                stat.put("last_used", cursor.getLong(cursor.getColumnIndex(AppDatabase.COL_LAST_USED)));
                stats.add(stat);
            }
            cursor.close();
        } catch (Exception e) {
            Log.e(TAG, "Error getting app usage stats", e);
        }
        return stats;
    }
    
    public void close() {
        if (db != null && db.isOpen()) {
            db.close();
        }
        if (dbHelper != null) {
            dbHelper.close();
        }
    }
}
