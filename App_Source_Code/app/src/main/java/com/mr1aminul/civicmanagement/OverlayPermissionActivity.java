package com.mr1aminul.civicmanagement;

import android.app.Activity;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.widget.Toast;

public class OverlayPermissionActivity extends Activity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            if (!Settings.canDrawOverlays(this)) {
                Intent intent = new Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                        Uri.parse("package:" + getPackageName()));
                startActivity(intent);
                Toast.makeText(this, getString(R.string.grant_overlay), Toast.LENGTH_LONG).show();
            } else {
                startService(new Intent(this, LeadBubbleService.class));
            }
        } else {
            startService(new Intent(this, LeadBubbleService.class));
        }
        finish();
    }
}