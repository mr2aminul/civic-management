package com.mr1aminul.civicmanagement;

import android.Manifest;
import android.app.AlertDialog;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.text.TextUtils;
import android.view.View;
import android.widget.Button;
import android.widget.ImageView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;

public class PermissionActivity extends AppCompatActivity {
    private static final String TAG = "PermissionActivity";

    private ImageView permissionIcon;
    private TextView permissionTitle;
    private TextView permissionDescription;
    private Button allowButton;
    private Button settingsButton;

    private int currentPermissionIndex = 0;
    private List<PermissionInfo> permissionsList = new ArrayList<>();

    private final ActivityResultLauncher<String[]> requestPermissionsLauncher =
            registerForActivityResult(new ActivityResultContracts.RequestMultiplePermissions(), result -> {
                handlePermissionResult(result);
            });

    private final ActivityResultLauncher<Intent> settingsLauncher =
            registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
                // After returning from settings, re-check the current permission(s)
                checkCurrentPermission();
            });

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_permission);

        initializeViews();
        setupPermissionsList();
        showNextPermission();
    }

    private void initializeViews() {
        permissionIcon = findViewById(R.id.permissionIcon);
        permissionTitle = findViewById(R.id.permissionTitle);
        permissionDescription = findViewById(R.id.permissionDescription);
        allowButton = findViewById(R.id.allowButton);
        settingsButton = findViewById(R.id.settingsButton);

        allowButton.setOnClickListener(v -> requestCurrentPermission());
        settingsButton.setOnClickListener(v -> openAppSettings());
    }

    private void setupPermissionsList() {
        permissionsList.clear();

        // Foreground location
        permissionsList.add(new PermissionInfo(
                Manifest.permission.ACCESS_FINE_LOCATION,
                R.drawable.ic_location_permission,
                "Location Access Required",
                "Location permission (foreground) is required for cross-matching attendance records.",
                new String[]{Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION},
                true
        ));

        // Background Location (Android 10+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            permissionsList.add(new PermissionInfo(
                    Manifest.permission.ACCESS_BACKGROUND_LOCATION,
                    R.drawable.ic_location_permission,
                    "Background Location Required",
                    "Background location access (Allow all the time) is required to track your location while the app is not in use.",
                    new String[]{Manifest.permission.ACCESS_BACKGROUND_LOCATION},
                    false
            ));
        }

        // Phone & Call Log
        permissionsList.add(new PermissionInfo(
                Manifest.permission.READ_PHONE_STATE,
                R.drawable.ic_phone_permission,
                "Phone Access Required",
                "Phone permission is required to monitor call activities for lead follow-up purposes.",
                new String[]{Manifest.permission.READ_PHONE_STATE, Manifest.permission.READ_CALL_LOG},
                false
        ));

        // Notifications (Android 13+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            permissionsList.add(new PermissionInfo(
                    Manifest.permission.POST_NOTIFICATIONS,
                    R.drawable.ic_notification_permission,
                    "Notification Access Required",
                    "Notification permission is required to receive important updates.",
                    new String[]{Manifest.permission.POST_NOTIFICATIONS},
                    false
            ));
        }

        // Install Unknown Apps (special)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            permissionsList.add(new PermissionInfo(
                    "REQUEST_INSTALL_PACKAGES",
                    R.drawable.ic_install_permission,
                    "Install Apps Permission Required",
                    "Allows installing updates from unknown sources for this app.",
                    new String[]{"REQUEST_INSTALL_PACKAGES"},
                    false
            ));
        }

        // Overlay (special)
        permissionsList.add(new PermissionInfo(
                "SYSTEM_ALERT_WINDOW",
                R.drawable.ic_overlay_permission,
                "Display Over Apps Required",
                "This permission allows the app to display important notifications and alerts over other apps.",
                new String[]{"SYSTEM_ALERT_WINDOW"},
                false
        ));
    }

    private void showNextPermission() {
        if (currentPermissionIndex >= permissionsList.size()) {
            startMainActivity();
            return;
        }

        PermissionInfo currentPermission = permissionsList.get(currentPermissionIndex);

        if (isPermissionGranted(currentPermission)) {
            currentPermissionIndex++;
            showNextPermission();
            return;
        }

        // Update UI
        permissionIcon.setImageResource(currentPermission.iconRes);
        permissionTitle.setText(currentPermission.title);
        permissionDescription.setText(currentPermission.description);

        // Decide which button to show based on whether the permission can be requested via system dialog
        if (isRuntimeRequestable(currentPermission)) {
            // Show Allow (system dialog)
            allowButton.setVisibility(View.VISIBLE);
            settingsButton.setVisibility(View.GONE);
            allowButton.setText("Allow Permission");
            // allowButton onClick already wired to requestCurrentPermission()
        } else {
            // Show Settings (special-case permissions and background location)
            allowButton.setVisibility(View.GONE);
            settingsButton.setVisibility(View.VISIBLE);
            settingsButton.setText("Open Settings");
        }

        // If permanently denied and runtime requestable, we should show settings instead
        if (!isRuntimeRequestable(currentPermission) == false && shouldShowSettingsButton(currentPermission)) {
            // if runtime but permanently denied => force show settings
            allowButton.setVisibility(View.GONE);
            settingsButton.setVisibility(View.VISIBLE);
            settingsButton.setText("Open Settings");
        }

        // long-press on Allow or Settings to show help
        allowButton.setOnLongClickListener(v -> {
            showHelpDialogFor(currentPermission);
            return true;
        });
        settingsButton.setOnLongClickListener(v -> {
            showHelpDialogFor(currentPermission);
            return true;
        });
    }

    /**
     * Returns true when this permission should be requested via runtime system dialog (Allow/Deny).
     * We treat these as runtime-requestable: READ_PHONE_STATE, READ_CALL_LOG, POST_NOTIFICATIONS (API 33+),
     * ACCESS_FINE_LOCATION / ACCESS_COARSE_LOCATION (foreground).
     *
     * Returns false for special settings-only permissions like SYSTEM_ALERT_WINDOW, REQUEST_INSTALL_PACKAGES,
     * and ACCESS_BACKGROUND_LOCATION (treated as settings-only per your request).
     */
    private boolean isRuntimeRequestable(PermissionInfo p) {
        // Special-case settings-only perms:
        if ("SYSTEM_ALERT_WINDOW".equals(p.permission) ||
                "REQUEST_INSTALL_PACKAGES".equals(p.permission) ||
                Manifest.permission.ACCESS_BACKGROUND_LOCATION.equals(p.permission)) {
            return false;
        }

        // If any permission in the list is a normal runtime permission, return true.
        for (String perm : p.permissions) {
            if (Manifest.permission.READ_PHONE_STATE.equals(perm)
                    || Manifest.permission.READ_CALL_LOG.equals(perm)
                    || Manifest.permission.POST_NOTIFICATIONS.equals(perm)
                    || Manifest.permission.ACCESS_FINE_LOCATION.equals(perm)
                    || Manifest.permission.ACCESS_COARSE_LOCATION.equals(perm)) {
                return true;
            }
            // You can add other runtime-perms here if needed
        }
        return false;
    }

    private boolean isPermissionGranted(PermissionInfo permissionInfo) {
        if ("SYSTEM_ALERT_WINDOW".equals(permissionInfo.permission)) {
            return Build.VERSION.SDK_INT < Build.VERSION_CODES.M || Settings.canDrawOverlays(this);
        }

        if ("REQUEST_INSTALL_PACKAGES".equals(permissionInfo.permission)) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                return getPackageManager().canRequestPackageInstalls();
            }
            return true;
        }

        for (String permission : permissionInfo.permissions) {
            if (ContextCompat.checkSelfPermission(this, permission) != PackageManager.PERMISSION_GRANTED) {
                return false;
            }
        }
        return true;
    }

    private boolean shouldShowSettingsButton(PermissionInfo permissionInfo) {
        // Special settings-based permissions
        if ("SYSTEM_ALERT_WINDOW".equals(permissionInfo.permission)) {
            return Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && !Settings.canDrawOverlays(this);
        }
        if ("REQUEST_INSTALL_PACKAGES".equals(permissionInfo.permission)) {
            return Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !getPackageManager().canRequestPackageInstalls();
        }

        boolean anyShouldShowRationale = false;
        boolean anyDenied = false;
        for (String permission : permissionInfo.permissions) {
            if (ContextCompat.checkSelfPermission(this, permission) != PackageManager.PERMISSION_GRANTED) {
                anyDenied = true;
                if (shouldShowRequestPermissionRationale(permission)) {
                    anyShouldShowRationale = true;
                }
            }
        }

        // If permission permanently denied (user checked "Don't ask again"), shouldShowRequestPermissionRationale returns false
        // and permission is denied -> we should show the settings button
        return anyDenied && !anyShouldShowRationale;
    }

    private void requestCurrentPermission() {
        PermissionInfo currentPermission = permissionsList.get(currentPermissionIndex);

        // If this permission is runtime-requestable then launch system dialog
        if (isRuntimeRequestable(currentPermission)) {
            // If it's foreground location group and background is in list later we still only request foreground here
            requestPermissionsLauncher.launch(currentPermission.permissions);
            return;
        }

        // Otherwise handle special-settings perms
        if ("SYSTEM_ALERT_WINDOW".equals(currentPermission.permission)) {
            requestOverlayPermission();
            return;
        }
        if ("REQUEST_INSTALL_PACKAGES".equals(currentPermission.permission)) {
            requestInstallPermission();
            return;
        }
        if (Manifest.permission.ACCESS_BACKGROUND_LOCATION.equals(currentPermission.permission)) {
            // We treat background location as settings-only per your instruction.
            showBackgroundLocationSettingsDialog();
            return;
        }

        // Fallback
        requestPermissionsLauncher.launch(currentPermission.permissions);
    }

    private void requestOverlayPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            Intent intent = new Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                    Uri.parse("package:" + getPackageName()));
            settingsLauncher.launch(intent);
        } else {
            Toast.makeText(this, "Overlay permission not required for your Android version.", Toast.LENGTH_SHORT).show();
        }
    }

    private void requestInstallPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Intent intent = new Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
                    Uri.parse("package:" + getPackageName()));
            settingsLauncher.launch(intent);
        } else {
            Toast.makeText(this, "Install packages permission not required for your Android version.", Toast.LENGTH_SHORT).show();
        }
    }

    private void openAppSettings() {
        openAppSettingsWithMessage(null);
    }

    private void openAppSettingsWithMessage(String optionalMessage) {
        Intent intent = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
                Uri.parse("package:" + getPackageName()));
        settingsLauncher.launch(intent);
        if (!TextUtils.isEmpty(optionalMessage)) {
            Toast.makeText(this, optionalMessage, Toast.LENGTH_LONG).show();
        }
    }

    private void handlePermissionResult(Map<String, Boolean> result) {
        boolean allGranted = true;
        for (Boolean granted : result.values()) {
            if (!granted) {
                allGranted = false;
                break;
            }
        }

        PermissionInfo currentPermission = permissionsList.get(currentPermissionIndex);

        if (allGranted) {
            currentPermissionIndex++;
            showNextPermission();
        } else {
            if (shouldShowSettingsButton(currentPermission)) {
                // show settings button instead of allow
                showNextPermission();
            } else {
                new AlertDialog.Builder(this)
                        .setTitle("Permission denied")
                        .setMessage("This permission is necessary for app features described. You can try again or open settings to enable it manually.")
                        .setPositiveButton("Try again", (d, w) -> requestCurrentPermission())
                        .setNegativeButton("Open Settings", (d, w) -> openAppSettings())
                        .setCancelable(false)
                        .show();
            }
        }
    }

    private void checkCurrentPermission() {
        PermissionInfo current = permissionsList.get(currentPermissionIndex);
        if (isPermissionGranted(current)) {
            currentPermissionIndex++;
        }
        showNextPermission();
    }

    private void showBackgroundLocationSettingsDialog() {
        new AlertDialog.Builder(this)
                .setTitle("Allow background location")
                .setMessage("To track location while the app is not in use, please choose \"Allow all the time\" in App permissions. We'll open the App's permission page where you can change this setting.")
                .setPositiveButton("Open App Permissions", (dialog, which) -> openAppSettingsWithMessage("Please tap Location → Allow all the time"))
                .setNegativeButton("Cancel", (d, w) -> {
                })
                .setCancelable(false)
                .show();
    }

    private int findPermissionIndex(String permission) {
        for (int i = 0; i < permissionsList.size(); i++) {
            PermissionInfo p = permissionsList.get(i);
            if (permission.equals(p.permission)) return i;
        }
        return -1;
    }

    private void showHelpDialogFor(PermissionInfo permissionInfo) {
        String helpText;
        if ("SYSTEM_ALERT_WINDOW".equals(permissionInfo.permission)) {
            helpText = "Steps to enable \"Display over other apps\":\n\n1. Open Settings → Apps → (Your App) → Advanced → Display over other apps (or Draw over other apps)\n2. Toggle Allow display over other apps to ON.\n\nIf you can't find it, open the App settings (Open Settings) and search for \"Display over\" or \"Draw over.\"";
        } else if ("REQUEST_INSTALL_PACKAGES".equals(permissionInfo.permission)) {
            helpText = "Steps to allow installing unknown apps:\n\n1. Open Settings → Apps → (Your App) → Install unknown apps (or Install other apps)\n2. Toggle Allow from this source to ON.\n\nIf you can't find it, open App settings and search for \"Install unknown\".";
        } else if (Manifest.permission.ACCESS_BACKGROUND_LOCATION.equals(permissionInfo.permission)) {
            helpText = "To set Location to 'Allow all the time':\n\n1. Open Settings → Apps → (Your App) → Permissions → Location\n2. Select 'Allow all the time' or 'Allow all the time while using the app' depending on your Android version.\n\nWe will open the App's permission page to help you.";
        } else {
            helpText = "Permission steps:\n\n1. When the system permission dialog appears tap Allow.\n2. If denied, try again. If you used \"Don't ask again\" you must open App Settings to enable.";
        }

        new AlertDialog.Builder(this)
                .setTitle("Help — How to enable")
                .setMessage(helpText)
                .setPositiveButton("Open App Settings", (d, w) -> openAppSettings())
                .setNeutralButton("Copy Steps", (d, w) -> {
                    copyToClipboard(helpText);
                    Toast.makeText(this, "Steps copied to clipboard. Paste them into your notes app.", Toast.LENGTH_SHORT).show();
                })
                .setNegativeButton("Close", null)
                .show();
    }

    private void copyToClipboard(String text) {
        ClipboardManager clipboard = (ClipboardManager) getSystemService(Context.CLIPBOARD_SERVICE);
        if (clipboard != null) {
            ClipData clip = ClipData.newPlainText("permission_steps", text);
            clipboard.setPrimaryClip(clip);
        }
    }

    private void startMainActivity() {
        Intent intent = new Intent(this, MainActivity.class);
        startActivity(intent);
        finish();
    }

    @Override
    public void onBackPressed() {
        // Prevent back press - user must grant permissions
        Toast.makeText(this, "Please grant all required permissions to continue", Toast.LENGTH_LONG).show();
    }

    private static class PermissionInfo {
        String permission;     // canonical identifier (or special string for settings-based)
        int iconRes;
        String title;
        String description;
        String[] permissions;  // runtime permission(s) to request
        boolean requiresBackgroundAfterGranted; // used for similar UX flows

        PermissionInfo(String permission, int iconRes, String title, String description, String[] permissions, boolean reqBg) {
            this.permission = permission;
            this.iconRes = iconRes;
            this.title = title;
            this.description = description;
            this.permissions = permissions;
            this.requiresBackgroundAfterGranted = reqBg;
        }
    }
}
