package com.mr1aminul.civicmanagement;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.NotificationManager;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.SharedPreferences;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkRequest;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.os.PowerManager;
import android.provider.Settings;
import android.util.Log;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;

public class MainActivity extends AppCompatActivity {
    private static final String TAG = "MainActivity";
    
    private WebView webView;
    private ProgressBar progressBar;
    private SwipeRefreshLayout swipeRefreshLayout;
    
    private static final String BASE_URL = "https://civicgroupbd.com/management/";
    private static final String COOKIE_DOMAIN = "https://civicgroupbd.com";
    private static final String PREFS_NAME = "app_prefs";
    private static final String PREF_USER_ID = "user_id2";
    private static final String OFFLINE_PAGE = "file:///android_asset/offline.html";
    private static final String CACHE_DIR = "asset_cache";
    
    private String currentUrl;
    private ConnectivityManager.NetworkCallback networkCallback;
    private boolean isOnline = false;

    private final BroadcastReceiver connectivityReceiver = new BroadcastReceiver() {
        @Override
        public void onReceive(Context context, Intent intent) {
            checkConnectivityAndReload();
        }
    };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        // Check permissions before proceeding
        if (!areAllPermissionsGranted()) {
            startActivity(new Intent(this, PermissionActivity.class));
            finish();
            return;
        }
        
        setContentView(R.layout.activity_main);
        
        initializeViews();
        setupWebView();
        setupSwipeRefresh();
        setupNetworkMonitoring();
        checkBatteryOptimization();
        startServices();
        
        loadInitialPage();
    }

    private boolean areAllPermissionsGranted() {
        // Check location permissions
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED ||
            ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            return false;
        }
        
        // Check background location (Android 10+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_BACKGROUND_LOCATION) != PackageManager.PERMISSION_GRANTED) {
                return false;
            }
        }
        
        // Check phone permissions
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_STATE) != PackageManager.PERMISSION_GRANTED ||
            ContextCompat.checkSelfPermission(this, Manifest.permission.READ_CALL_LOG) != PackageManager.PERMISSION_GRANTED) {
            return false;
        }
        
        // Check notification permission (Android 13+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                return false;
            }
        }
        
        // Check install unknown apps permission (Android 8+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            if (!getPackageManager().canRequestPackageInstalls()) {
                return false;
            }
        }
        
        // Check overlay permission
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            if (!Settings.canDrawOverlays(this)) {
                return false;
            }
        }
        
        return true;
    }

    private void initializeViews() {
        webView = findViewById(R.id.webView);
        progressBar = findViewById(R.id.progressBar);
        swipeRefreshLayout = findViewById(R.id.swipeRefreshLayout);
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(true);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            settings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        }

        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(webView, true);
        }

        webView.setWebViewClient(new CustomWebViewClient());
        webView.setWebChromeClient(new CustomWebChromeClient());
    }

    private void setupSwipeRefresh() {
        swipeRefreshLayout.setOnRefreshListener(() -> {
            if (isOnline) {
                new Handler(Looper.getMainLooper()).postDelayed(() -> {
                    webView.reload();
                    swipeRefreshLayout.setRefreshing(false);
                }, 300);
            } else {
                checkConnectivityAndReload();
                swipeRefreshLayout.setRefreshing(false);
            }
        });
        
        swipeRefreshLayout.setColorSchemeResources(
            android.R.color.holo_blue_bright,
            android.R.color.holo_green_light,
            android.R.color.holo_orange_light,
            android.R.color.holo_red_light
        );
        
        swipeRefreshLayout.setDistanceToTriggerSync(150);
    }

    private void setupNetworkMonitoring() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm != null) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                networkCallback = new ConnectivityManager.NetworkCallback() {
                    @Override
                    public void onAvailable(Network network) {
                        runOnUiThread(() -> {
                            isOnline = true;
                            if (webView.getUrl() != null && webView.getUrl().equals(OFFLINE_PAGE)) {
                                loadInitialPage();
                            }
                        });
                    }

                    @Override
                    public void onLost(Network network) {
                        runOnUiThread(() -> {
                            isOnline = false;
                        });
                    }
                };
                cm.registerNetworkCallback(new NetworkRequest.Builder().build(), networkCallback);
            } else {
                IntentFilter filter = new IntentFilter(ConnectivityManager.CONNECTIVITY_ACTION);
                registerReceiver(connectivityReceiver, filter);
            }
        }
        
        isOnline = isNetworkAvailable();
    }

    private void startServices() {
        Intent serviceIntent = new Intent(this, UnifiedBackgroundService.class);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            startForegroundService(serviceIntent);
        } else {
            startService(serviceIntent);
        }
        
        // Start call tracking service
        Intent callServiceIntent = new Intent(this, CallTrackingService.class);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            startForegroundService(callServiceIntent);
        } else {
            startService(callServiceIntent);
        }

    }

    private void loadInitialPage() {
        String appVersion = getAppVersion();
        Uri.Builder uriBuilder = Uri.parse(BASE_URL).buildUpon()
                .appendQueryParameter("app_version", appVersion);
        
        Intent intent = getIntent();
        String urlFromNotification = intent.getStringExtra("url");
        if (urlFromNotification != null && !urlFromNotification.isEmpty()) {
            uriBuilder = Uri.parse(urlFromNotification).buildUpon()
                    .appendQueryParameter("app_version", appVersion);
        }
        
        currentUrl = uriBuilder.build().toString();
        
        if (isOnline) {
            webView.loadUrl(currentUrl);
        } else {
            webView.loadUrl(OFFLINE_PAGE);
        }
    }

    private void checkConnectivityAndReload() {
        new Handler(Looper.getMainLooper()).postDelayed(() -> {
            boolean wasOnline = isOnline;
            isOnline = isNetworkAvailable();
            
            if (!wasOnline && isOnline) {
                if (webView.getUrl() != null && webView.getUrl().equals(OFFLINE_PAGE)) {
                    webView.loadUrl(currentUrl);
                }
                Toast.makeText(this, "Connection restored", Toast.LENGTH_SHORT).show();
            } else if (!isOnline) {
                if (webView.getUrl() != null && !webView.getUrl().equals(OFFLINE_PAGE)) {
                    webView.loadUrl(OFFLINE_PAGE);
                }
            }
        }, 1000);
    }

    private boolean isNetworkAvailable() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) return false;
        
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            Network network = cm.getActiveNetwork();
            if (network == null) return false;
            NetworkCapabilities capabilities = cm.getNetworkCapabilities(network);
            return capabilities != null && 
                   (capabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) ||
                    capabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) ||
                    capabilities.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET));
        } else {
            android.net.NetworkInfo networkInfo = cm.getActiveNetworkInfo();
            return networkInfo != null && networkInfo.isConnected();
        }
    }

    private void checkBatteryOptimization() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            PowerManager pm = (PowerManager) getSystemService(Context.POWER_SERVICE);
            if (pm != null && !pm.isIgnoringBatteryOptimizations(getPackageName())) {
                try {
                    Intent intent = new Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS);
                    intent.setData(Uri.parse("package:" + getPackageName()));
                    startActivity(intent);
                } catch (Exception e) {
                    Log.e(TAG, "Error requesting battery optimization", e);
                }
            }
        }
    }

    private String getAppVersion() {
        try {
            PackageInfo pInfo = getPackageManager().getPackageInfo(getPackageName(), 0);
            return pInfo.versionName;
        } catch (PackageManager.NameNotFoundException e) {
            return "unknown";
        }
    }

    private class CustomWebViewClient extends WebViewClient {
        @Override
        public WebResourceResponse shouldInterceptRequest(WebView view, WebResourceRequest request) {
            return interceptAssetRequest(request);
        }

        @Override
        public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            String url = request.getUrl().toString();
            if (url.startsWith("tel:") || url.startsWith("whatsapp:") || url.startsWith("mailto:")) {
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, request.getUrl()));
                } catch (Exception e) {
                    Log.e(TAG, "Error opening external app", e);
                }
                return true;
            }
            return false;
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            super.onReceivedError(view, request, error);
            if (request.isForMainFrame() && !isOnline) {
                view.loadUrl(OFFLINE_PAGE);
            }
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            super.onPageFinished(view, url);
            progressBar.setVisibility(View.GONE);
            
            String cookies = CookieManager.getInstance().getCookie(COOKIE_DOMAIN);
            String userId = getUserIdFromCookie(cookies);
            if (userId != null && !userId.isEmpty()) {
                SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
                prefs.edit().putString(PREF_USER_ID, userId).apply();
            }
        }
    }

    private class CustomWebChromeClient extends WebChromeClient {
        @Override
        public void onProgressChanged(WebView view, int newProgress) {
            if (newProgress < 100) {
                progressBar.setVisibility(View.VISIBLE);
                progressBar.setProgress(newProgress);
            } else {
                progressBar.setVisibility(View.GONE);
            }
        }
    }

    private WebResourceResponse interceptAssetRequest(WebResourceRequest request) {
        String url = request.getUrl().toString();
        if (url.contains("/manage/assets/") && 
            url.matches(".*\\.(js|css|png|jpg|jpeg|gif|webp|bmp|svg)(\\?.*)?")) {
            
            Uri uri = request.getUrl();
            String fileName = uri.getLastPathSegment();
            if (fileName == null) return null;
            
            String version = uri.getQueryParameter("version");
            String localFileName = (version != null && !version.isEmpty()) 
                ? fileName + "_" + version : fileName;
            
            File cacheDir = new File(getCacheDir(), CACHE_DIR);
            if (!cacheDir.exists()) cacheDir.mkdirs();
            File localFile = new File(cacheDir, localFileName);
            
            if (localFile.exists()) {
                try {
                    InputStream is = new FileInputStream(localFile);
                    return new WebResourceResponse(getMimeType(fileName), "UTF-8", is);
                } catch (Exception e) {
                    Log.e(TAG, "Error reading cached file", e);
                }
            } else if (isOnline) {
                try {
                    HttpURLConnection conn = (HttpURLConnection) new URL(url).openConnection();
                    conn.setConnectTimeout(5000);
                    conn.setReadTimeout(5000);
                    
                    InputStream is = conn.getInputStream();
                    FileOutputStream fos = new FileOutputStream(localFile);
                    
                    byte[] buffer = new byte[4096];
                    int bytesRead;
                    while ((bytesRead = is.read(buffer)) != -1) {
                        fos.write(buffer, 0, bytesRead);
                    }
                    
                    fos.close();
                    is.close();
                    conn.disconnect();
                    
                    InputStream cached = new FileInputStream(localFile);
                    return new WebResourceResponse(getMimeType(fileName), "UTF-8", cached);
                } catch (Exception e) {
                    Log.e(TAG, "Error caching asset", e);
                }
            }
        }
        return null;
    }

    private String getMimeType(String fileName) {
        if (fileName.endsWith(".js")) return "application/javascript";
        if (fileName.endsWith(".css")) return "text/css";
        if (fileName.endsWith(".png")) return "image/png";
        if (fileName.endsWith(".jpg") || fileName.endsWith(".jpeg")) return "image/jpeg";
        if (fileName.endsWith(".gif")) return "image/gif";
        if (fileName.endsWith(".webp")) return "image/webp";
        if (fileName.endsWith(".svg")) return "image/svg+xml";
        return "application/octet-stream";
    }

    private String getUserIdFromCookie(String cookieString) {
        if (cookieString == null) return null;
        for (String cookie : cookieString.split(";")) {
            String[] parts = cookie.split("=");
            if (parts.length == 2 && parts[0].trim().equals(PREF_USER_ID)) {
                return parts[1].trim();
            }
        }
        return null;
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N && networkCallback != null) {
            ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
            if (cm != null) {
                cm.unregisterNetworkCallback(networkCallback);
            }
        } else {
            try {
                unregisterReceiver(connectivityReceiver);
            } catch (Exception e) {
                // Receiver not registered
            }
        }
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }
}
