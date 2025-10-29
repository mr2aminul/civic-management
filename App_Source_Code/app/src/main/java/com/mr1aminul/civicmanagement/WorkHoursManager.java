package com.mr1aminul.civicmanagement;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;

import java.util.Calendar;

public class WorkHoursManager {
    private static final String TAG = "WorkHoursManager";
    private static final String PREFS_NAME = "work_hours_prefs";
    private static final String KEY_WORK_START_HOUR = "work_start_hour";
    private static final String KEY_WORK_END_HOUR = "work_end_hour";

    private static final int DEFAULT_START_HOUR = 8;
    private static final int DEFAULT_END_HOUR = 21;

    private Context context;
    private SharedPreferences prefs;

    public WorkHoursManager(Context context) {
        this.context = context.getApplicationContext();
        this.prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
    }

    public boolean isWithinWorkHours() {
        Calendar now = Calendar.getInstance();
        int currentHour = now.get(Calendar.HOUR_OF_DAY);
        int currentMinute = now.get(Calendar.MINUTE);

        int startHour = prefs.getInt(KEY_WORK_START_HOUR, DEFAULT_START_HOUR);
        int endHour = prefs.getInt(KEY_WORK_END_HOUR, DEFAULT_END_HOUR);

        int currentTimeInMinutes = (currentHour * 60) + currentMinute;
        int startTimeInMinutes = startHour * 60;
        int endTimeInMinutes = endHour * 60;

        boolean isWorkHours = currentTimeInMinutes >= startTimeInMinutes && currentTimeInMinutes < endTimeInMinutes;

        Log.d(TAG, "Current time: " + currentHour + ":" + currentMinute +
                   ", Work hours: " + startHour + ":00 - " + endHour + ":00" +
                   ", Is work hours: " + isWorkHours);

        return isWorkHours;
    }

    public long getNextWorkHourChangeMillis() {
        Calendar now = Calendar.getInstance();
        Calendar next = (Calendar) now.clone();

        int startHour = prefs.getInt(KEY_WORK_START_HOUR, DEFAULT_START_HOUR);
        int endHour = prefs.getInt(KEY_WORK_END_HOUR, DEFAULT_END_HOUR);

        if (isWithinWorkHours()) {
            next.set(Calendar.HOUR_OF_DAY, endHour);
            next.set(Calendar.MINUTE, 0);
            next.set(Calendar.SECOND, 0);
            next.set(Calendar.MILLISECOND, 0);
        } else {
            next.set(Calendar.HOUR_OF_DAY, startHour);
            next.set(Calendar.MINUTE, 0);
            next.set(Calendar.SECOND, 0);
            next.set(Calendar.MILLISECOND, 0);

            if (next.before(now)) {
                next.add(Calendar.DAY_OF_MONTH, 1);
            }
        }

        return next.getTimeInMillis();
    }

    public String getWorkHoursDescription() {
        int startHour = prefs.getInt(KEY_WORK_START_HOUR, DEFAULT_START_HOUR);
        int endHour = prefs.getInt(KEY_WORK_END_HOUR, DEFAULT_END_HOUR);

        return String.format("%02d:00 - %02d:00", startHour, endHour);
    }

    public int getStartHour() {
        return prefs.getInt(KEY_WORK_START_HOUR, DEFAULT_START_HOUR);
    }

    public int getEndHour() {
        return prefs.getInt(KEY_WORK_END_HOUR, DEFAULT_END_HOUR);
    }

    public void setWorkHours(int startHour, int endHour) {
        if (startHour < 0 || startHour > 23 || endHour < 0 || endHour > 23 || startHour >= endHour) {
            Log.e(TAG, "Invalid work hours: " + startHour + " - " + endHour);
            return;
        }

        prefs.edit()
            .putInt(KEY_WORK_START_HOUR, startHour)
            .putInt(KEY_WORK_END_HOUR, endHour)
            .apply();

        Log.d(TAG, "Work hours updated: " + startHour + ":00 - " + endHour + ":00");
    }
}
