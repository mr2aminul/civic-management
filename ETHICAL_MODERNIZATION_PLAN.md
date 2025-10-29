# Ethical Workforce Management App - Modernization Plan

## Overview
This document outlines the complete modernization plan to transform your app into an ethical, Google Play-compliant workforce management solution with full employee consent and transparency.

## ✅ Core Principles

1. **Full Transparency**: Employees know exactly what's being monitored
2. **Explicit Consent**: Clear consent screen before any tracking begins
3. **Work Hours Only**: Automatic enforcement of 8:00 AM - 9:00 PM tracking window
4. **Visible Indicators**: Persistent notification showing when tracking is active
5. **Google Play Compliant**: Follows all Google Play policies for employee monitoring apps

---

## 📋 Implementation Checklist

### Phase 1: Consent & Transparency ✅ STARTED

**Files Created:**
- `ConsentActivity.java` - Consent screen with terms and conditions
- `activity_consent.xml` - UI layout for consent
- `WorkHoursManager.java` - Manages work hours (8 AM - 9 PM)

**What's Needed:**
1. Update `SplashActivity.java` to check for consent first
2. Update `AndroidManifest.xml` to add ConsentActivity
3. Update permission descriptions to be more transparent

### Phase 2: Foreground Notification (Required for Google Play)

**Modify: `UnifiedBackgroundService.java`**

Current notification is minimal. Change to:
```java
private Notification createForegroundNotification() {
    WorkHoursManager workHours = new WorkHoursManager(this);
    boolean isWorkHours = workHours.isWithinWorkHours();

    String title = isWorkHours ? "⚠️ Workforce Tracking Active" : "Workforce Management";
    String text = isWorkHours ?
        "Location, calls, and app usage are being monitored during work hours (" + workHours.getWorkHoursDescription() + ")" :
        "Tracking paused outside work hours";

    return new NotificationCompat.Builder(this, CHANNEL_ID)
        .setContentTitle(title)
        .setContentText(text)
        .setSmallIcon(R.drawable.ic_notification)
        .setPriority(NotificationCompat.PRIORITY_HIGH)
        .setOngoing(true)
        .setShowWhen(true)
        .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
        .setStyle(new NotificationCompat.BigTextStyle().bigText(text))
        .build();
}
```

**Update Notification Channel:**
```java
private void createNotificationChannel() {
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
        NotificationChannel channel = new NotificationChannel(
            CHANNEL_ID,
            "Employee Monitoring Status",
            NotificationManager.IMPORTANCE_HIGH  // Changed from MIN to HIGH
        );
        channel.setDescription("Shows when employee monitoring is active");
        channel.setShowBadge(true);
        channel.enableVibration(false);
        channel.enableLights(false);
        channel.setLockscreenVisibility(Notification.VISIBILITY_PUBLIC);

        NotificationManager manager = getSystemService(NotificationManager.class);
        if (manager != null) {
            manager.createNotificationChannel(channel);
        }
    }
}
```

### Phase 3: Work Hours Enforcement

**Modify: `UnifiedBackgroundService.java`**

Add work hours checking to all tracking functions:

```java
private void setupLocationTracking() {
    WorkHoursManager workHours = new WorkHoursManager(this);

    try {
        fusedLocationClient = LocationServices.getFusedLocationProviderClient(this);

        LocationRequest locationRequest = new LocationRequest.Builder(
            Priority.PRIORITY_HIGH_ACCURACY,
            Config.LOCATION_SYNC_INTERVAL
        )
        .setMinUpdateIntervalMillis(Config.LOCATION_SYNC_INTERVAL / 2)
        .build();

        locationCallback = new LocationCallback() {
            @Override
            public void onLocationResult(LocationResult locationResult) {
                if (locationResult == null) return;

                // Only track location during work hours
                if (workHours.isWithinWorkHours()) {
                    currentLocation = locationResult.getLastLocation();
                } else {
                    currentLocation = null;
                }
            }
        };

        fusedLocationClient.requestLocationUpdates(locationRequest, locationCallback, Looper.getMainLooper());

    } catch (SecurityException e) {
        Log.e(TAG, "Location permission not granted", e);
    }
}

private void sendLocationToServer(Location location) {
    WorkHoursManager workHours = new WorkHoursManager(this);

    // Only send location during work hours
    if (!workHours.isWithinWorkHours()) {
        Log.d(TAG, "Outside work hours, skipping location sync");
        return;
    }

    // ... existing location sending code
}
```

**Add Work Hours Scheduler:**

```java
private void scheduleWorkHoursCheck() {
    WorkHoursManager workHours = new WorkHoursManager(this);
    long nextChangeMillis = workHours.getNextWorkHourChangeMillis();

    Intent intent = new Intent(this, WorkHoursReceiver.class);
    PendingIntent pendingIntent = PendingIntent.getBroadcast(
        this,
        999,
        intent,
        PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
    );

    AlarmManager alarmManager = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
    if (alarmManager != null) {
        alarmManager.setExactAndAllowWhileIdle(
            AlarmManager.RTC_WAKEUP,
            nextChangeMillis,
            pendingIntent
        );
    }

    // Update notification to show work hours status
    updateForegroundNotification();
}
```

**Create: `WorkHoursReceiver.java`**

```java
public class WorkHoursReceiver extends BroadcastReceiver {
    @Override
    public void onReceive(Context context, Intent intent) {
        WorkHoursManager workHours = new WorkHoursManager(context);

        if (workHours.isWithinWorkHours()) {
            // Start work hours tracking
            Intent serviceIntent = new Intent(context, UnifiedBackgroundService.class);
            serviceIntent.putExtra("action", "START_WORK_HOURS");
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(serviceIntent);
            } else {
                context.startService(serviceIntent);
            }
        } else {
            // Pause tracking (but keep service running for notifications)
            Intent serviceIntent = new Intent(context, UnifiedBackgroundService.class);
            serviceIntent.putExtra("action", "STOP_WORK_HOURS");
            context.startService(serviceIntent);
        }
    }
}
```

### Phase 4: Fix Alarm System (Multiple Concurrent Alarms)

**Problem:** Current system doesn't handle multiple simultaneous alarms well.

**Solution: Create `EnhancedAlarmManager.java`**

```java
public class EnhancedAlarmManager {
    private static final String TAG = "EnhancedAlarmManager";
    private static final String PREFS_NAME = "enhanced_alarm_prefs";
    private static final String KEY_ACTIVE_ALARMS = "active_alarms";

    private Context context;
    private SharedPreferences prefs;

    public EnhancedAlarmManager(Context context) {
        this.context = context.getApplicationContext();
        this.prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
    }

    public void scheduleAlarm(AlarmData alarmData) {
        // Check if alarm already exists
        if (isAlarmScheduled(alarmData.id)) {
            Log.d(TAG, "Alarm already scheduled: " + alarmData.id);
            return;
        }

        Intent intent = new Intent(context, EnhancedAlarmReceiver.class);
        intent.putExtra("alarm_data", alarmData.toJson());

        // Use unique request code for each alarm
        int requestCode = alarmData.id.hashCode();

        PendingIntent pendingIntent = PendingIntent.getBroadcast(
            context,
            requestCode,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );

        AlarmManager alarmManager = (AlarmManager) context.getSystemService(Context.ALARM_SERVICE);
        if (alarmManager != null) {
            long triggerTime = alarmData.triggerTimeMillis;

            if (triggerTime <= System.currentTimeMillis()) {
                triggerTime = System.currentTimeMillis() + 5000;
            }

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                if (alarmManager.canScheduleExactAlarms()) {
                    alarmManager.setExactAndAllowWhileIdle(
                        AlarmManager.RTC_WAKEUP,
                        triggerTime,
                        pendingIntent
                    );
                } else {
                    alarmManager.setAndAllowWhileIdle(
                        AlarmManager.RTC_WAKEUP,
                        triggerTime,
                        pendingIntent
                    );
                }
            } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                alarmManager.setExactAndAllowWhileIdle(
                    AlarmManager.RTC_WAKEUP,
                    triggerTime,
                    pendingIntent
                );
            } else {
                alarmManager.setExact(
                    AlarmManager.RTC_WAKEUP,
                    triggerTime,
                    pendingIntent
                );
            }

            saveAlarmToPrefs(alarmData);
            Log.d(TAG, "Alarm scheduled: " + alarmData.id + " at " + new Date(triggerTime));
        }
    }

    public void cancelAlarm(String alarmId) {
        Intent intent = new Intent(context, EnhancedAlarmReceiver.class);
        int requestCode = alarmId.hashCode();

        PendingIntent pendingIntent = PendingIntent.getBroadcast(
            context,
            requestCode,
            intent,
            PendingIntent.FLAG_NO_CREATE | PendingIntent.FLAG_IMMUTABLE
        );

        if (pendingIntent != null) {
            AlarmManager alarmManager = (AlarmManager) context.getSystemService(Context.ALARM_SERVICE);
            if (alarmManager != null) {
                alarmManager.cancel(pendingIntent);
            }
            pendingIntent.cancel();
        }

        removeAlarmFromPrefs(alarmId);
        Log.d(TAG, "Alarm cancelled: " + alarmId);
    }

    private boolean isAlarmScheduled(String alarmId) {
        try {
            String alarmsJson = prefs.getString(KEY_ACTIVE_ALARMS, "[]");
            JSONArray alarms = new JSONArray(alarmsJson);

            for (int i = 0; i < alarms.length(); i++) {
                JSONObject alarm = alarms.getJSONObject(i);
                if (alarm.getString("id").equals(alarmId)) {
                    return true;
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "Error checking alarm", e);
        }
        return false;
    }

    // Additional helper methods...
}
```

**Create: `EnhancedAlarmReceiver.java`**

```java
public class EnhancedAlarmReceiver extends BroadcastReceiver {
    private static final String TAG = "EnhancedAlarmReceiver";
    private static final String PREFS_NAME = "alarm_display_prefs";
    private static final long ALARM_DEBOUNCE_MILLIS = 5000; // 5 seconds

    @Override
    public void onReceive(Context context, Intent intent) {
        String alarmJson = intent.getStringExtra("alarm_data");
        if (alarmJson == null) return;

        try {
            JSONObject alarmObj = new JSONObject(alarmJson);
            String alarmId = alarmObj.getString("id");

            // Check if we recently displayed this alarm (debounce)
            SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
            long lastDisplayTime = prefs.getLong("last_display_" + alarmId, 0);
            long currentTime = System.currentTimeMillis();

            if (currentTime - lastDisplayTime < ALARM_DEBOUNCE_MILLIS) {
                Log.d(TAG, "Alarm debounced: " + alarmId);
                return;
            }

            // Save display time
            prefs.edit().putLong("last_display_" + alarmId, currentTime).apply();

            // Acquire wake lock
            PowerManager pm = (PowerManager) context.getSystemService(Context.POWER_SERVICE);
            PowerManager.WakeLock wakeLock = null;
            if (pm != null) {
                wakeLock = pm.newWakeLock(
                    PowerManager.FULL_WAKE_LOCK |
                    PowerManager.ACQUIRE_CAUSES_WAKEUP |
                    PowerManager.ON_AFTER_RELEASE,
                    "CivicManagement::EnhancedAlarmWakelock"
                );
                wakeLock.acquire(3 * 60 * 1000L); // 3 minutes
            }

            // Launch alarm activity
            Intent alarmIntent = new Intent(context, EnhancedAlarmActivity.class);
            alarmIntent.putExtra("alarm_data", alarmJson);
            alarmIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK |
                                Intent.FLAG_ACTIVITY_NO_USER_ACTION |
                                Intent.FLAG_ACTIVITY_EXCLUDE_FROM_RECENTS);
            context.startActivity(alarmIntent);

            // Release wake lock after delay
            if (wakeLock != null) {
                final PowerManager.WakeLock finalWakeLock = wakeLock;
                new Handler(Looper.getMainLooper()).postDelayed(() -> {
                    if (finalWakeLock.isHeld()) {
                        finalWakeLock.release();
                    }
                }, 3 * 60 * 1000L);
            }

        } catch (Exception e) {
            Log.e(TAG, "Error handling alarm", e);
        }
    }
}
```

### Phase 5: Improve Alarm for Deep Sleep/Idle/Locked States

**Key Changes:**

1. Use `setExactAndAllowWhileIdle()` for Android 6+
2. Use `setAlarmClock()` for critical alarms
3. Add full wake lock to turn screen on
4. Use `FLAG_ACTIVITY_NO_USER_ACTION` to work on lockscreen

**Update: `EnhancedAlarmActivity.java`**

```java
@Override
protected void onCreate(Bundle savedInstanceState) {
    super.onCreate(savedInstanceState);

    // Show on lock screen and turn screen on
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
        setShowWhenLocked(true);
        setTurnScreenOn(true);
        KeyguardManager keyguardManager = (KeyguardManager) getSystemService(Context.KEYGUARD_SERVICE);
        if (keyguardManager != null) {
            keyguardManager.requestDismissKeyguard(this, null);
        }
    } else {
        getWindow().addFlags(
            WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED |
            WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD |
            WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON |
            WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON
        );
    }

    setContentView(R.layout.activity_enhanced_alarm);

    // Rest of initialization...
}
```

### Phase 6: Enhanced Lead Floating Bubble

**Create: `ModernLeadBubbleService.java`**

Improve the existing LeadBubbleService with:
- Better UI design
- Multiple bubble support
- Swipe to dismiss
- Drag and drop positioning
- Memory leak fixes

Key improvements:
```java
private void createModernBubbleView(LeadData leadData) {
    View bubbleView = LayoutInflater.from(this).inflate(R.layout.modern_lead_bubble, null);

    // Setup modern UI elements
    TextView titleView = bubbleView.findViewById(R.id.bubbleTitle);
    TextView messageView = bubbleView.findViewById(R.id.bubbleMessage);
    ImageView avatarView = bubbleView.findViewById(R.id.bubbleAvatar);

    titleView.setText(leadData.title);
    messageView.setText(leadData.message);

    // Add touch handling for drag and swipe
    bubbleView.setOnTouchListener(new BubbleTouchListener());

    // Add window parameters
    WindowManager.LayoutParams params = new WindowManager.LayoutParams(
        WindowManager.LayoutParams.WRAP_CONTENT,
        WindowManager.LayoutParams.WRAP_CONTENT,
        Build.VERSION.SDK_INT >= Build.VERSION_CODES.O ?
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY :
            WindowManager.LayoutParams.TYPE_PHONE,
        WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE |
        WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS,
        PixelFormat.TRANSLUCENT
    );

    params.gravity = Gravity.TOP | Gravity.START;
    params.x = 0;
    params.y = 100;

    windowManager.addView(bubbleView, params);
    activeBubbles.put(leadData.id, bubbleView);
}
```

### Phase 7: App Usage Tracking

**Create: `AppUsageTracker.java`**

```java
public class AppUsageTracker {
    private static final String TAG = "AppUsageTracker";

    private Context context;
    private UsageStatsManager usageStatsManager;

    public AppUsageTracker(Context context) {
        this.context = context.getApplicationContext();
        this.usageStatsManager = (UsageStatsManager) context.getSystemService(Context.USAGE_STATS_SERVICE);
    }

    public List<AppUsageData> getAppUsageStats(long startTime, long endTime) {
        List<AppUsageData> appUsageList = new ArrayList<>();

        if (usageStatsManager == null) {
            return appUsageList;
        }

        Map<String, UsageStats> stats = usageStatsManager.queryAndAggregateUsageStats(startTime, endTime);
        PackageManager pm = context.getPackageManager();

        for (Map.Entry<String, UsageStats> entry : stats.entrySet()) {
            UsageStats usageStats = entry.getValue();

            if (usageStats.getTotalTimeInForeground() > 0) {
                try {
                    ApplicationInfo appInfo = pm.getApplicationInfo(usageStats.getPackageName(), 0);
                    String appName = pm.getApplicationLabel(appInfo).toString();

                    AppUsageData data = new AppUsageData(
                        usageStats.getPackageName(),
                        appName,
                        usageStats.getTotalTimeInForeground(),
                        usageStats.getLastTimeUsed()
                    );

                    appUsageList.add(data);
                } catch (PackageManager.NameNotFoundException e) {
                    // App not found
                }
            }
        }

        return appUsageList;
    }

    public List<String> getInstalledApps() {
        List<String> installedApps = new ArrayList<>();
        PackageManager pm = context.getPackageManager();
        List<ApplicationInfo> apps = pm.getInstalledApplications(PackageManager.GET_META_DATA);

        for (ApplicationInfo app : apps) {
            if ((app.flags & ApplicationInfo.FLAG_SYSTEM) == 0) {
                String appName = pm.getApplicationLabel(app).toString();
                installedApps.add(appName + " (" + app.packageName + ")");
            }
        }

        return installedApps;
    }

    public void syncAppUsageToServer(String userId) {
        WorkHoursManager workHours = new WorkHoursManager(context);

        if (!workHours.isWithinWorkHours()) {
            Log.d(TAG, "Outside work hours, skipping app usage sync");
            return;
        }

        long endTime = System.currentTimeMillis();
        long startTime = endTime - (24 * 60 * 60 * 1000); // Last 24 hours

        List<AppUsageData> usageData = getAppUsageStats(startTime, endTime);
        List<String> installedApps = getInstalledApps();

        // Send to server
        sendAppDataToServer(userId, usageData, installedApps);
    }
}
```

### Phase 8: Supabase Database Migration

**Create migration: `employee_tracking_schema.sql`**

```sql
/*
  # Employee Tracking Database Schema

  1. New Tables
    - `employees`
      - Employee basic information
    - `employee_locations`
      - GPS location history during work hours
    - `employee_call_logs`
      - Call history tracking
    - `employee_app_usage`
      - App usage statistics
    - `employee_installed_apps`
      - List of installed apps
    - `employee_work_sessions`
      - Work session tracking
    - `lead_follow_ups`
      - Lead follow-up tracking
    - `alarms`
      - Scheduled alarms/reminders

  2. Security
    - Enable RLS on all tables
    - Add policies for authenticated users
*/

-- Employees table
CREATE TABLE IF NOT EXISTS employees (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id text UNIQUE NOT NULL,
  full_name text NOT NULL,
  email text,
  phone text,
  consent_given boolean DEFAULT false,
  consent_timestamp timestamptz,
  work_start_hour integer DEFAULT 8,
  work_end_hour integer DEFAULT 21,
  is_active boolean DEFAULT true,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

ALTER TABLE employees ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can read own employee data"
  ON employees FOR SELECT
  TO authenticated
  USING (auth.uid()::text = user_id);

CREATE POLICY "System can insert employee data"
  ON employees FOR INSERT
  TO authenticated
  WITH CHECK (true);

CREATE POLICY "Users can update own employee data"
  ON employees FOR UPDATE
  TO authenticated
  USING (auth.uid()::text = user_id)
  WITH CHECK (auth.uid()::text = user_id);

-- Employee locations table
CREATE TABLE IF NOT EXISTS employee_locations (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id text NOT NULL,
  latitude decimal(10, 8) NOT NULL,
  longitude decimal(11, 8) NOT NULL,
  accuracy float,
  during_work_hours boolean DEFAULT true,
  recorded_at timestamptz NOT NULL,
  created_at timestamptz DEFAULT now()
);

CREATE INDEX idx_employee_locations_user_id ON employee_locations(user_id);
CREATE INDEX idx_employee_locations_recorded_at ON employee_locations(recorded_at);

ALTER TABLE employee_locations ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Managers can view location data"
  ON employee_locations FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "System can insert location data"
  ON employee_locations FOR INSERT
  TO authenticated
  WITH CHECK (true);

-- Employee call logs table
CREATE TABLE IF NOT EXISTS employee_call_logs (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id text NOT NULL,
  phone_number text NOT NULL,
  contact_name text,
  call_type text NOT NULL,
  call_status text,
  duration integer DEFAULT 0,
  recorded_at timestamptz NOT NULL,
  created_at timestamptz DEFAULT now()
);

CREATE INDEX idx_employee_call_logs_user_id ON employee_call_logs(user_id);
CREATE INDEX idx_employee_call_logs_recorded_at ON employee_call_logs(recorded_at);

ALTER TABLE employee_call_logs ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Managers can view call logs"
  ON employee_call_logs FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "System can insert call logs"
  ON employee_call_logs FOR INSERT
  TO authenticated
  WITH CHECK (true);

-- Employee app usage table
CREATE TABLE IF NOT EXISTS employee_app_usage (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id text NOT NULL,
  package_name text NOT NULL,
  app_name text NOT NULL,
  usage_time_ms bigint DEFAULT 0,
  last_used_at timestamptz,
  date date NOT NULL,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now(),
  UNIQUE(user_id, package_name, date)
);

CREATE INDEX idx_employee_app_usage_user_id ON employee_app_usage(user_id);
CREATE INDEX idx_employee_app_usage_date ON employee_app_usage(date);

ALTER TABLE employee_app_usage ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Managers can view app usage"
  ON employee_app_usage FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "System can manage app usage"
  ON employee_app_usage FOR ALL
  TO authenticated
  USING (true)
  WITH CHECK (true);

-- Employee installed apps table
CREATE TABLE IF NOT EXISTS employee_installed_apps (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id text NOT NULL,
  package_name text NOT NULL,
  app_name text NOT NULL,
  detected_at timestamptz DEFAULT now(),
  is_active boolean DEFAULT true,
  created_at timestamptz DEFAULT now(),
  UNIQUE(user_id, package_name)
);

CREATE INDEX idx_employee_installed_apps_user_id ON employee_installed_apps(user_id);

ALTER TABLE employee_installed_apps ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Managers can view installed apps"
  ON employee_installed_apps FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "System can manage installed apps"
  ON employee_installed_apps FOR ALL
  TO authenticated
  USING (true)
  WITH CHECK (true);

-- Work sessions table
CREATE TABLE IF NOT EXISTS employee_work_sessions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id text NOT NULL,
  session_start timestamptz NOT NULL,
  session_end timestamptz,
  duration_minutes integer,
  created_at timestamptz DEFAULT now()
);

CREATE INDEX idx_employee_work_sessions_user_id ON employee_work_sessions(user_id);
CREATE INDEX idx_employee_work_sessions_start ON employee_work_sessions(session_start);

ALTER TABLE employee_work_sessions ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Managers can view work sessions"
  ON employee_work_sessions FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "System can manage work sessions"
  ON employee_work_sessions FOR ALL
  TO authenticated
  USING (true)
  WITH CHECK (true);
```

### Phase 9: Modernized PHP API Endpoints

**Create: `/app_api/v2/employee_tracking.php`**

```php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../common.php';

$action = req_param('action');
$user_id = req_param('user_id');

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'user_id is required']);
    exit;
}

try {
    switch ($action) {
        case 'sync_location':
            handleLocationSync($user_id);
            break;
        case 'sync_call_logs':
            handleCallLogsSync($user_id);
            break;
        case 'sync_app_usage':
            handleAppUsageSync($user_id);
            break;
        case 'sync_installed_apps':
            handleInstalledAppsSync($user_id);
            break;
        case 'start_work_session':
            handleStartWorkSession($user_id);
            break;
        case 'end_work_session':
            handleEndWorkSession($user_id);
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

function handleLocationSync($user_id) {
    global $db;
    $data = read_json_body();

    if (!isset($data['locations']) || !is_array($data['locations'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'locations array required']);
        return;
    }

    $synced = 0;
    $failed = 0;

    foreach ($data['locations'] as $location) {
        $lat = $location['latitude'] ?? null;
        $lng = $location['longitude'] ?? null;
        $accuracy = $location['accuracy'] ?? null;
        $timestamp = $location['timestamp'] ?? time() * 1000;
        $during_work_hours = $location['during_work_hours'] ?? true;

        if ($lat === null || $lng === null) {
            $failed++;
            continue;
        }

        $insert = $db->insert('employee_locations', [
            'user_id' => $user_id,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $accuracy,
            'during_work_hours' => $during_work_hours ? 1 : 0,
            'recorded_at' => date('Y-m-d H:i:s', $timestamp / 1000)
        ]);

        if ($insert) {
            $synced++;
        } else {
            $failed++;
        }
    }

    echo json_encode([
        'status' => 'success',
        'synced' => $synced,
        'failed' => $failed
    ]);
}

function handleCallLogsSync($user_id) {
    global $db;
    $data = read_json_body();

    if (!isset($data['call_logs']) || !is_array($data['call_logs'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'call_logs array required']);
        return;
    }

    $synced = 0;

    foreach ($data['call_logs'] as $call) {
        $phone = $call['phone_number'] ?? null;
        $type = $call['call_type'] ?? 'unknown';
        $status = $call['call_status'] ?? 'unknown';
        $duration = $call['duration'] ?? 0;
        $contact_name = $call['contact_name'] ?? null;
        $timestamp = $call['timestamp'] ?? time() * 1000;

        if (!$phone) continue;

        $insert = $db->insert('employee_call_logs', [
            'user_id' => $user_id,
            'phone_number' => $phone,
            'contact_name' => $contact_name,
            'call_type' => $type,
            'call_status' => $status,
            'duration' => $duration,
            'recorded_at' => date('Y-m-d H:i:s', $timestamp / 1000)
        ]);

        if ($insert) $synced++;
    }

    echo json_encode([
        'status' => 'success',
        'synced' => $synced
    ]);
}

function handleAppUsageSync($user_id) {
    global $db;
    $data = read_json_body();

    if (!isset($data['app_usage']) || !is_array($data['app_usage'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'app_usage array required']);
        return;
    }

    $synced = 0;
    $today = date('Y-m-d');

    foreach ($data['app_usage'] as $app) {
        $package = $app['package_name'] ?? null;
        $name = $app['app_name'] ?? null;
        $usage_time = $app['usage_time_ms'] ?? 0;
        $last_used = $app['last_used'] ?? time() * 1000;

        if (!$package) continue;

        // Check if record exists for today
        $existing = $db->where('user_id', $user_id)
                      ->where('package_name', $package)
                      ->where('date', $today)
                      ->getOne('employee_app_usage');

        if ($existing) {
            // Update existing record
            $db->where('id', $existing->id)
               ->update('employee_app_usage', [
                   'usage_time_ms' => $db->inc($usage_time),
                   'last_used_at' => date('Y-m-d H:i:s', $last_used / 1000),
                   'updated_at' => date('Y-m-d H:i:s')
               ]);
        } else {
            // Insert new record
            $db->insert('employee_app_usage', [
                'user_id' => $user_id,
                'package_name' => $package,
                'app_name' => $name,
                'usage_time_ms' => $usage_time,
                'last_used_at' => date('Y-m-d H:i:s', $last_used / 1000),
                'date' => $today
            ]);
        }

        $synced++;
    }

    echo json_encode([
        'status' => 'success',
        'synced' => $synced
    ]);
}

// Additional handlers...
?>
```

### Phase 10: Google Play Compliance Updates

**Update: `AndroidManifest.xml`**

Add required declarations:

```xml
<manifest>
    <!-- Declare foreground service types -->
    <uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
    <uses-permission android:name="android.permission.FOREGROUND_SERVICE_LOCATION" />
    <uses-permission android:name="android.permission.FOREGROUND_SERVICE_DATA_SYNC" />

    <!-- Declare QUERY_ALL_PACKAGES usage -->
    <uses-permission android:name="android.permission.QUERY_ALL_PACKAGES"
        tools:ignore="QueryAllPackagesPermission" />

    <application>
        <!-- Add metadata for Google Play -->
        <meta-data
            android:name="com.google.android.gms.permission.AD_ID"
            android:value="false" />

        <!-- Consent Activity (First screen) -->
        <activity
            android:name=".ConsentActivity"
            android:exported="false"
            android:theme="@style/Theme.AppCompat.Light.NoActionBar" />

        <!-- Enhanced service declarations -->
        <service
            android:name=".UnifiedBackgroundService"
            android:exported="false"
            android:foregroundServiceType="location|dataSync"
            android:stopWithTask="false"
            android:permission="android.permission.BIND_JOB_SERVICE" />

        <!-- Add receivers -->
        <receiver
            android:name=".WorkHoursReceiver"
            android:exported="false" />

        <receiver
            android:name=".EnhancedAlarmReceiver"
            android:exported="false" />
    </application>

    <!-- Declare queries for installed apps -->
    <queries>
        <intent>
            <action android:name="android.intent.action.MAIN" />
        </intent>
    </queries>
</manifest>
```

**Create: Google Play Store Listing Description**

```
TRANSPARENT WORKFORCE MANAGEMENT

This app is designed for legitimate employee monitoring with full consent and transparency.

⚠️ IMPORTANT NOTICE:
• This app requires explicit employee consent before use
• Monitoring is ONLY active during work hours (8:00 AM - 9:00 PM)
• A persistent notification shows when tracking is active
• This app should only be installed on company-owned devices or with employee agreement

FEATURES:
✓ Location tracking during work hours for field service coordination
✓ Call log tracking for customer follow-up management
✓ App usage monitoring for productivity insights
✓ Lead and task notifications with smart reminders
✓ Automatic work hours enforcement
✓ Transparent monitoring with visible indicators

EMPLOYEE RIGHTS:
• Full disclosure of what data is collected
• Consent required before any tracking begins
• Tracking limited to configured work hours only
• Visible notification when monitoring is active
• Right to access collected data

PRIVACY & SECURITY:
• All data transmitted using HTTPS encryption
• Data stored on secure company servers
• Access restricted to authorized personnel
• Compliant with employee monitoring regulations

This app is intended for workforce management purposes only and must be used in accordance with local labor laws and with full employee consent.
```

---

## 🔒 Security & Privacy Improvements

1. **Data Encryption**: All API calls use HTTPS
2. **Token Authentication**: Implement JWT tokens for API requests
3. **Data Minimization**: Only collect necessary data
4. **Retention Policies**: Auto-delete old data after 90 days
5. **Access Controls**: Role-based access to employee data

---

## 📱 UI/UX Improvements

1. **Modern Material Design 3**: Update all UI components
2. **Dark Mode Support**: Add dark theme
3. **Better Error Handling**: User-friendly error messages
4. **Offline Support**: Queue data when offline, sync when online
5. **Performance**: Optimize battery usage and network calls

---

## 🧪 Testing Checklist

- [ ] Consent flow works correctly
- [ ] Work hours enforcement (8 AM - 9 PM)
- [ ] Foreground notification always visible during work hours
- [ ] Multiple alarms trigger correctly
- [ ] Alarms work during deep sleep/locked screen
- [ ] Lead bubbles display properly
- [ ] App usage tracking accurate
- [ ] Location tracking only during work hours
- [ ] Call logs sync correctly
- [ ] All permissions requested with clear explanations
- [ ] Google Play Policy compliance verified

---

## 📦 Files to Remove (Cleanup)

Remove these unnecessary or problematic files:

```
- BackgroundService.java (replaced by UnifiedBackgroundService)
- EnhancedBackgroundService.java (merged into Unified)
- AlarmAccessibilityService.java (not needed, causes Play Store issues)
- AlarmOverlayService.java (replaced by EnhancedAlarmActivity)
- NotifAlarmService.java (redundant)
- FloatingNotificationService.java (needs complete rewrite)
- LeadsActivity.java (if not used)
- CustomSwipeRefreshLayout.java (use standard)
```

---

## 🚀 Deployment Steps

1. Update Android app with all changes
2. Test thoroughly on multiple Android versions (8.0 - 14.0)
3. Update PHP backend with new endpoints
4. Create Supabase database schema
5. Update server configuration
6. Create Google Play Store listing with proper disclosures
7. Submit for review with clear explanation of monitoring use case
8. Provide documentation for employees explaining monitoring

---

## 📞 Support & Maintenance

After deployment:
1. Monitor crash reports via Google Play Console
2. Gather employee feedback
3. Regular security audits
4. Keep dependencies updated
5. Comply with any regulation changes

---

This plan transforms your app into an ethical, transparent, and Google Play-compliant workforce management solution while maintaining all the functionality you need.
