package com.mr1aminul.civicmanagement;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
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
        
        // Location Permission
        permissionsList.add(new PermissionInfo(
            Manifest.permission.ACCESS_FINE_LOCATION,
            R.drawable.ic_location_permission,
            "Location Access Required",
            "Location permission is required for cross-matching attendance records. This helps verify your work location and ensures accurate attendance tracking.\n\nPlease allow location permission for better service and accurate attendance management.",
            new String[]{Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION}
        ));
        
        // Background Location (Android 10+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            permissionsList.add(new PermissionInfo(
                Manifest.permission.ACCESS_BACKGROUND_LOCATION,
                R.drawable.ic_location_permission,
                "Background Location Required",
                "Background location access is needed to track your location even when the app is not actively being used. This ensures continuous attendance monitoring during work hours.\n\nThis permission is essential for accurate work hour tracking.",
                new String[]{Manifest.permission.ACCESS_BACKGROUND_LOCATION}
        ));
    }
    
    // Phone Permission
    permissionsList.add(new PermissionInfo(
        Manifest.permission.READ_PHONE_STATE,
        R.drawable.ic_phone_permission,
        "Phone Access Required",
        "Phone permission is required to monitor call activities for lead follow-up purposes. Your call data will be used exclusively for lead management and follow-up tracking.\n\nYou can view all your call data in the lead details section. No personal information is shared outside the organization.",
        new String[]{Manifest.permission.READ_PHONE_STATE, Manifest.permission.READ_CALL_LOG}
    ));
    
    // Notification Permission (Android 13+)
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
        permissionsList.add(new PermissionInfo(
            Manifest.permission.POST_NOTIFICATIONS,
            R.drawable.ic_notification_permission,
            "Notification Access Required",
            "Notification permission is required to receive important updates about leads, tasks, and work assignments.\n\nThis ensures you never miss critical business communications and can respond promptly to urgent matters.",
            new String[]{Manifest.permission.POST_NOTIFICATIONS}
        ));
    }
    
    // Install Unknown Apps Permission (Android 8+)
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
        permissionsList.add(new PermissionInfo(
            "REQUEST_INSTALL_PACKAGES",
            R.drawable.ic_install_permission,
            "Install Apps Permission Required",
            "This permission allows the app to automatically install updates when available. This ensures you always have the latest version with important security updates and new features.\n\nThis permission is essential for seamless app updates without manual intervention.",
            new String[]{"REQUEST_INSTALL_PACKAGES"}
        ));
    }
    
    // Overlay Permission
    permissionsList.add(new PermissionInfo(
        "SYSTEM_ALERT_WINDOW",
        R.drawable.ic_overlay_permission,
        "Display Over Apps Required",
        "This permission allows the app to display important notifications and alerts over other apps. This is essential for urgent lead notifications and critical business alerts.\n\nThis ensures you receive important notifications even when using other applications.",
        new String[]{"SYSTEM_ALERT_WINDOW"}
    ));
}

    private void showNextPermission() {
        if (currentPermissionIndex >= permissionsList.size()) {
            // All permissions granted, proceed to main app
            startMainActivity();
            return;
        }
        
        PermissionInfo currentPermission = permissionsList.get(currentPermissionIndex);
        
        // Check if permission is already granted
        if (isPermissionGranted(currentPermission)) {
            currentPermissionIndex++;
            showNextPermission();
            return;
        }
        
        // Show permission UI
        permissionIcon.setImageResource(currentPermission.iconRes);
        permissionTitle.setText(currentPermission.title);
        permissionDescription.setText(currentPermission.description);
        
        // Show appropriate button
        if (shouldShowSettingsButton(currentPermission)) {
            allowButton.setVisibility(View.GONE);
            settingsButton.setVisibility(View.VISIBLE);
            settingsButton.setText("Open Settings");
        } else {
            allowButton.setVisibility(View.VISIBLE);
            settingsButton.setVisibility(View.GONE);
            allowButton.setText("Allow Permission");
        }
    }

    private boolean isPermissionGranted(PermissionInfo permissionInfo) {
        if ("SYSTEM_ALERT_WINDOW".equals(permissionInfo.permission)) {
            return Build.VERSION.SDK_INT < Build.VERSION_CODES.M || Settings.canDrawOverlays(this);
        }
        
        if ("REQUEST_INSTALL_PACKAGES".equals(permissionInfo.permission)) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                return getPackageManager().canRequestPackageInstalls();
            }
            return true; // Not required for older versions
        }
        
        for (String permission : permissionInfo.permissions) {
            if (ContextCompat.checkSelfPermission(this, permission) != PackageManager.PERMISSION_GRANTED) {
                return false;
            }
        }
        return true;
    }

    private boolean shouldShowSettingsButton(PermissionInfo permissionInfo) {
        if ("SYSTEM_ALERT_WINDOW".equals(permissionInfo.permission)) {
            return Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && !Settings.canDrawOverlays(this);
        }
        
        if ("REQUEST_INSTALL_PACKAGES".equals(permissionInfo.permission)) {
            return Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !getPackageManager().canRequestPackageInstalls();
        }
        
        // Check if we should show rationale (user denied before)
        for (String permission : permissionInfo.permissions) {
            if (shouldShowRequestPermissionRationale(permission)) {
                return true;
            }
        }
        return false;
    }

    private void requestCurrentPermission() {
        PermissionInfo currentPermission = permissionsList.get(currentPermissionIndex);
        
        if ("SYSTEM_ALERT_WINDOW".equals(currentPermission.permission)) {
            requestOverlayPermission();
        } else if ("REQUEST_INSTALL_PACKAGES".equals(currentPermission.permission)) {
            requestInstallPermission();
        } else {
            requestPermissionsLauncher.launch(currentPermission.permissions);
        }
    }

    private void requestOverlayPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            Intent intent = new Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION);
            intent.setData(Uri.parse("package:" + getPackageName()));
            settingsLauncher.launch(intent);
        }
    }

    private void requestInstallPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Intent intent = new Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES);
            intent.setData(Uri.parse("package:" + getPackageName()));
            settingsLauncher.launch(intent);
        }
    }

    private void openAppSettings() {
        Intent intent = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS);
        intent.setData(Uri.parse("package:" + getPackageName()));
        settingsLauncher.launch(intent);
    }

    private void handlePermissionResult(java.util.Map<String, Boolean> result) {
        boolean allGranted = true;
        for (Boolean granted : result.values()) {
            if (!granted) {
                allGranted = false;
                break;
            }
        }
        
        if (allGranted) {
            currentPermissionIndex++;
            showNextPermission();
        } else {
            // Permission denied, show settings button
            showNextPermission();
        }
    }

    private void checkCurrentPermission() {
        // Recheck current permission after returning from settings
        showNextPermission();
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
        String permission;
        int iconRes;
        String title;
        String description;
        String[] permissions;
        
        PermissionInfo(String permission, int iconRes, String title, String description, String[] permissions) {
            this.permission = permission;
            this.iconRes = iconRes;
            this.title = title;
            this.description = description;
            this.permissions = permissions;
        }
    }
}
