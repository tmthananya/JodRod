import React, { useRef, useState, useEffect } from 'react';
import {
  StyleSheet,
  SafeAreaView,
  View,
  Text,
  TextInput,
  ActivityIndicator,
  TouchableOpacity,
  BackHandler,
  Platform,
  StatusBar,
  NativeModules,
  Linking
} from 'react-native';
import { WebView } from 'react-native-webview';

// Target port and route matching desktop app
const APP_PORT = '8088';
const APP_ROUTE = '/#map';

// Known hosts
const CURRENT_MAC_IP = '192.168.0.247';
const LOCALHOST_IP = '127.0.0.1';
const HOTSPOT_IP = '172.20.10.2';
const HOME_WIFI_IP = '192.168.70.187';

/**
 * PUBLIC_PRIVACY_POLICY_URL:
 * Publicly accessible HTTPS URL for Google Play Console submission.
 * Update via process.env.EXPO_PUBLIC_PRIVACY_POLICY_URL or edit this variable before release.
 * If empty, falls back to the current server's /privacy-policy.html.
 */
export const PUBLIC_PRIVACY_POLICY_URL = process.env.EXPO_PUBLIC_PRIVACY_POLICY_URL || '';

/**
 * Automatically determine the best initial URL based on platform & environment:
 * - Desktop Web: http://127.0.0.1:8088/#map (or current host)
 * - Mobile Expo Go: dynamically detects Metro server IP from bundle scriptURL
 */
function getInitialUrl() {
  if (Platform.OS === 'web') {
    if (typeof window !== 'undefined' && window.location && window.location.hostname) {
      return `http://${window.location.hostname}:${APP_PORT}${APP_ROUTE}`;
    }
    return `http://${LOCALHOST_IP}:${APP_PORT}${APP_ROUTE}`;
  }

  // On Mobile (iOS / Android), extract host IP where Metro bundled the app from
  const scriptURL = NativeModules?.SourceCode?.scriptURL;
  if (scriptURL) {
    const match = scriptURL.match(/^https?:\/\/([^:/]+)/);
    if (match && match[1] && match[1] !== 'localhost' && match[1] !== '127.0.0.1') {
      return `http://${match[1]}:${APP_PORT}${APP_ROUTE}`;
    }
  }

  // Fallback to active Mac IP
  return `http://${CURRENT_MAC_IP}:${APP_PORT}${APP_ROUTE}`;
}

export default function App() {
  const webViewRef = useRef(null);
  const [currentUrl, setCurrentUrl] = useState(getInitialUrl());
  const [customInput, setCustomInput] = useState('');
  const [canGoBack, setCanGoBack] = useState(false);
  const [hasError, setHasError] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [showConfig, setShowConfig] = useState(false);

  const handleOpenPrivacyPolicy = async () => {
    let targetUrl = PUBLIC_PRIVACY_POLICY_URL;
    if (!targetUrl) {
      try {
        const parsed = new URL(currentUrl);
        targetUrl = `${parsed.protocol}//${parsed.host}/privacy-policy.html`;
      } catch {
        const base = currentUrl.split('/#')[0].split('#')[0].replace(/\/+$/, '');
        targetUrl = `${base}/privacy-policy.html`;
      }
    }

    try {
      const supported = await Linking.canOpenURL(targetUrl);
      if (supported) {
        await Linking.openURL(targetUrl);
      } else if (webViewRef.current) {
        setCurrentUrl(targetUrl);
      }
    } catch (err) {
      console.warn('Could not open external privacy policy URL:', err);
      if (webViewRef.current) {
        setCurrentUrl(targetUrl);
      }
    }
  };

  // Handle Android physical back button
  useEffect(() => {
    if (Platform.OS !== 'android') return;
    const onBackPress = () => {
      if (canGoBack && webViewRef.current) {
        webViewRef.current.goBack();
        return true;
      }
      return false;
    };
    const subscription = BackHandler.addEventListener('hardwareBackPress', onBackPress);
    return () => subscription.remove();
  }, [canGoBack]);

  const handleReload = () => {
    setHasError(false);
    setErrorMessage('');
    if (webViewRef.current) {
      webViewRef.current.reload();
    }
  };

  const handleSwitchUrl = (newUrl) => {
    setHasError(false);
    setErrorMessage('');
    setCurrentUrl(newUrl);
  };

  const handleApplyCustom = () => {
    let url = customInput.trim();
    if (!url) return;
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      if (url.includes(':')) {
        url = `http://${url}`;
      } else {
        url = `http://${url}:${APP_PORT}${APP_ROUTE}`;
      }
    }
    handleSwitchUrl(url);
    setShowConfig(false);
  };

  // Render Web-compatible container on Desktop browsers (Expo Web)
  if (Platform.OS === 'web') {
    return (
      <SafeAreaView style={styles.safeArea}>
        <StatusBar barStyle="light-content" backgroundColor="#0f172a" />
        <View style={styles.webHeader}>
          <Text style={styles.webTitle}>🚗 JodRod (Desktop Mode)</Text>
          <View style={styles.webActions}>
            <TouchableOpacity
              style={styles.webPrivacyBtn}
              onPress={handleOpenPrivacyPolicy}
            >
              <Text style={styles.webPrivacyBtnText}>Privacy Policy 🛡️</Text>
            </TouchableOpacity>
            <Text style={styles.webUrlText}>{currentUrl}</Text>
            <TouchableOpacity
              style={styles.openBrowserBtn}
              onPress={() => Linking.openURL(currentUrl)}
            >
              <Text style={styles.openBrowserBtnText}>Open Directly in Tab ↗</Text>
            </TouchableOpacity>
          </View>
        </View>
        <iframe
          src={currentUrl}
          style={{ width: '100%', height: 'calc(100vh - 44px)', border: 'none' }}
          title="JodRod Web"
        />
      </SafeAreaView>
    );
  }

  // Render Native Mobile Container (iOS / Android in Expo Go)
  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="light-content" backgroundColor="#0f172a" />

      {/* Top Quick Bar (Tap to change IP) */}
      <View style={styles.topBar}>
        <TouchableOpacity
          style={styles.topBarInfo}
          onPress={() => setShowConfig(!showConfig)}
        >
          <Text style={styles.topBarLabel}>Server:</Text>
          <Text style={styles.topBarUrl} numberOfLines={1}>{currentUrl}</Text>
          <Text style={styles.topBarChevron}>{showConfig ? '▲' : '▼'}</Text>
        </TouchableOpacity>
        <View style={styles.topBarRight}>
          <TouchableOpacity
            style={styles.privacyPillBtn}
            onPress={handleOpenPrivacyPolicy}
            accessibilityLabel="Privacy Policy"
          >
            <Text style={styles.privacyPillText}>Privacy</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.reloadMiniBtn} onPress={handleReload}>
            <Text style={styles.reloadMiniText}>🔄</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Collapsible IP Switcher */}
      {showConfig && (
        <View style={styles.configDrawer}>
          <Text style={styles.configTitle}>Select or Enter Server IP:</Text>
          <View style={styles.presetRow}>
            <TouchableOpacity
              style={[styles.presetChip, currentUrl.includes(CURRENT_MAC_IP) && styles.presetChipActive]}
              onPress={() => {
                handleSwitchUrl(`http://${CURRENT_MAC_IP}:${APP_PORT}${APP_ROUTE}`);
                setShowConfig(false);
              }}
            >
              <Text style={styles.presetChipText}>Phone Wi-Fi ({CURRENT_MAC_IP}:{APP_PORT})</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.presetChip, currentUrl.includes(LOCALHOST_IP) && styles.presetChipActive]}
              onPress={() => {
                handleSwitchUrl(`http://${LOCALHOST_IP}:${APP_PORT}${APP_ROUTE}`);
                setShowConfig(false);
              }}
            >
              <Text style={styles.presetChipText}>Desktop ({LOCALHOST_IP}:{APP_PORT})</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.presetChip, currentUrl.includes(HOTSPOT_IP) && styles.presetChipActive]}
              onPress={() => {
                handleSwitchUrl(`http://${HOTSPOT_IP}:${APP_PORT}${APP_ROUTE}`);
                setShowConfig(false);
              }}
            >
              <Text style={styles.presetChipText}>Hotspot ({HOTSPOT_IP}:{APP_PORT})</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.presetChip, currentUrl.includes(HOME_WIFI_IP) && styles.presetChipActive]}
              onPress={() => {
                handleSwitchUrl(`http://${HOME_WIFI_IP}:${APP_PORT}${APP_ROUTE}`);
                setShowConfig(false);
              }}
            >
              <Text style={styles.presetChipText}>Alt Wi-Fi ({HOME_WIFI_IP})</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.inputRow}>
            <TextInput
              style={styles.ipInput}
              placeholder="e.g. 192.168.1.44:8088 or IP"
              placeholderTextColor="#64748b"
              value={customInput}
              onChangeText={setCustomInput}
              autoCapitalize="none"
              autoCorrect={false}
            />
            <TouchableOpacity style={styles.applyBtn} onPress={handleApplyCustom}>
              <Text style={styles.applyBtnText}>Connect</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.drawerFooterRow}>
            <TouchableOpacity style={styles.drawerPrivacyLink} onPress={handleOpenPrivacyPolicy}>
              <Text style={styles.drawerPrivacyText}>🛡️ View Privacy Policy (Google Play) ↗</Text>
            </TouchableOpacity>
          </View>
        </View>
      )}

      {hasError ? (
        <View style={styles.errorContainer}>
          <Text style={styles.errorIcon}>⚠️</Text>
          <Text style={styles.errorTitle}>Cannot Connect to Server</Text>
          <Text style={styles.errorSubtitle}>
            Attempted URL:{'\n'}
            <Text style={styles.urlText}>{currentUrl}</Text>
          </Text>

          <View style={styles.helpBox}>
            <Text style={styles.helpHeader}>Quick Connection Tips:</Text>
            <Text style={styles.helpText}>
              • Make sure your phone & Mac are on the <Text style={styles.helpBold}>same Wi-Fi</Text>.{'\n'}
              • Your desktop URL is <Text style={styles.helpBold}>http://{LOCALHOST_IP}:{APP_PORT}{APP_ROUTE}</Text>.{'\n'}
              • For your phone, tap <Text style={styles.helpBold}>{CURRENT_MAC_IP}:{APP_PORT}</Text> below.{'\n'}
              • PHP server is running on port {APP_PORT}.
            </Text>
          </View>

          {errorMessage ? (
            <Text style={styles.errorDetail}>{errorMessage}</Text>
          ) : null}

          <View style={styles.presetBox}>
            <Text style={styles.presetTitle}>Tap to Switch Server:</Text>
            <View style={styles.presetGrid}>
              <TouchableOpacity
                style={styles.errorPresetBtn}
                onPress={() => handleSwitchUrl(`http://${CURRENT_MAC_IP}:${APP_PORT}${APP_ROUTE}`)}
              >
                <Text style={styles.errorPresetBtnText}>Phone on Wi-Fi ({CURRENT_MAC_IP}:{APP_PORT})</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.errorPresetBtn}
                onPress={() => handleSwitchUrl(`http://${LOCALHOST_IP}:${APP_PORT}${APP_ROUTE}`)}
              >
                <Text style={styles.errorPresetBtnText}>Desktop Local ({LOCALHOST_IP}:{APP_PORT})</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.errorPresetBtn}
                onPress={() => handleSwitchUrl(`http://${HOTSPOT_IP}:${APP_PORT}${APP_ROUTE}`)}
              >
                <Text style={styles.errorPresetBtnText}>Hotspot ({HOTSPOT_IP}:{APP_PORT})</Text>
              </TouchableOpacity>
            </View>
          </View>

          <TouchableOpacity style={styles.retryButton} onPress={handleReload}>
            <Text style={styles.retryButtonText}>🔄 Retry Connection</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.errorPrivacyBtn} onPress={handleOpenPrivacyPolicy}>
            <Text style={styles.errorPrivacyBtnText}>🛡️ View Privacy Policy</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <WebView
          ref={webViewRef}
          source={{ uri: currentUrl }}
          style={styles.webview}
          javaScriptEnabled={true}
          domStorageEnabled={true}
          startInLoadingState={true}
          scalesPageToFit={true}
          allowsInlineMediaPlayback={true}
          originWhitelist={['*']}
          renderLoading={() => (
            <View style={styles.loadingContainer}>
              <ActivityIndicator size="large" color="#3b82f6" />
              <Text style={styles.loadingText}>Loading JodRod...</Text>
              <Text style={styles.loadingSubtext}>{currentUrl}</Text>
            </View>
          )}
          onNavigationStateChange={(navState) => {
            setCanGoBack(navState.canGoBack);
          }}
          onShouldStartLoadWithRequest={(request) => {
            if (request.url && request.url.includes('privacy-policy.html')) {
              Linking.openURL(request.url).catch(() => {});
              return false;
            }
            return true;
          }}
          onError={(syntheticEvent) => {
            const { nativeEvent } = syntheticEvent;
            setHasError(true);
            setErrorMessage(nativeEvent.description || 'Connection refused or timed out');
          }}
          onHttpError={(syntheticEvent) => {
            const { nativeEvent } = syntheticEvent;
            if (nativeEvent.statusCode >= 400) {
              setHasError(true);
              setErrorMessage(`Server HTTP Error: ${nativeEvent.statusCode}`);
            }
          }}
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#0f172a',
  },
  webHeader: {
    height: 44,
    backgroundColor: '#0f172a',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#1e293b',
  },
  webTitle: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '700',
  },
  webActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  webUrlText: {
    color: '#94a3b8',
    fontSize: 12,
  },
  openBrowserBtn: {
    backgroundColor: '#2563eb',
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 6,
  },
  openBrowserBtnText: {
    color: '#ffffff',
    fontSize: 12,
    fontWeight: '600',
  },
  topBar: {
    height: 38,
    backgroundColor: '#0f172a',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#1e293b',
  },
  topBarInfo: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  topBarLabel: {
    color: '#64748b',
    fontSize: 11,
    fontWeight: '600',
  },
  topBarUrl: {
    color: '#38bdf8',
    fontSize: 12,
    fontWeight: '600',
    flexShrink: 1,
  },
  topBarChevron: {
    color: '#94a3b8',
    fontSize: 10,
  },
  reloadMiniBtn: {
    padding: 6,
  },
  reloadMiniText: {
    fontSize: 14,
  },
  configDrawer: {
    backgroundColor: '#1e293b',
    padding: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#334155',
  },
  configTitle: {
    color: '#cbd5e1',
    fontSize: 12,
    fontWeight: '600',
    marginBottom: 8,
  },
  presetRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginBottom: 10,
  },
  presetChip: {
    backgroundColor: '#334155',
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 6,
  },
  presetChipActive: {
    backgroundColor: '#2563eb',
  },
  presetChipText: {
    color: '#ffffff',
    fontSize: 11,
    fontWeight: '500',
  },
  inputRow: {
    flexDirection: 'row',
    gap: 8,
  },
  ipInput: {
    flex: 1,
    backgroundColor: '#0f172a',
    borderWidth: 1,
    borderColor: '#334155',
    borderRadius: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    color: '#ffffff',
    fontSize: 13,
  },
  applyBtn: {
    backgroundColor: '#2563eb',
    paddingHorizontal: 14,
    justifyContent: 'center',
    borderRadius: 6,
  },
  applyBtnText: {
    color: '#ffffff',
    fontSize: 13,
    fontWeight: '600',
  },
  webview: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  loadingContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#0f172a',
  },
  loadingText: {
    marginTop: 14,
    color: '#e2e8f0',
    fontSize: 16,
    fontWeight: '600',
  },
  loadingSubtext: {
    marginTop: 6,
    color: '#64748b',
    fontSize: 12,
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#0f172a',
  },
  errorIcon: {
    fontSize: 44,
    marginBottom: 12,
  },
  errorTitle: {
    color: '#f87171',
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 6,
    textAlign: 'center',
  },
  errorSubtitle: {
    color: '#cbd5e1',
    fontSize: 13,
    textAlign: 'center',
    marginBottom: 14,
    lineHeight: 18,
  },
  urlText: {
    color: '#38bdf8',
    fontWeight: '600',
  },
  helpBox: {
    alignSelf: 'stretch',
    backgroundColor: '#1e293b',
    padding: 14,
    borderRadius: 8,
    marginBottom: 14,
  },
  helpHeader: {
    color: '#f1f5f9',
    fontSize: 13,
    fontWeight: '700',
    marginBottom: 6,
  },
  helpText: {
    color: '#94a3b8',
    fontSize: 12,
    lineHeight: 18,
  },
  helpBold: {
    color: '#38bdf8',
    fontWeight: '600',
  },
  errorDetail: {
    color: '#fca5a5',
    fontSize: 12,
    marginBottom: 12,
    textAlign: 'center',
  },
  presetBox: {
    alignSelf: 'stretch',
    marginBottom: 16,
  },
  presetTitle: {
    color: '#94a3b8',
    fontSize: 12,
    fontWeight: '600',
    marginBottom: 8,
    textAlign: 'center',
  },
  presetGrid: {
    gap: 8,
  },
  errorPresetBtn: {
    backgroundColor: '#334155',
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 6,
    alignItems: 'center',
  },
  errorPresetBtnText: {
    color: '#e2e8f0',
    fontSize: 13,
    fontWeight: '600',
  },
  retryButton: {
    backgroundColor: '#2563eb',
    paddingVertical: 12,
    paddingHorizontal: 28,
    borderRadius: 8,
  },
  retryButtonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '600',
  },
  topBarRight: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  privacyPillBtn: {
    backgroundColor: '#1e293b',
    borderWidth: 1,
    borderColor: '#38bdf8',
    paddingVertical: 3,
    paddingHorizontal: 8,
    borderRadius: 4,
  },
  privacyPillText: {
    color: '#38bdf8',
    fontSize: 11,
    fontWeight: '700',
  },
  webPrivacyBtn: {
    backgroundColor: '#0369a1',
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 6,
  },
  webPrivacyBtnText: {
    color: '#ffffff',
    fontSize: 12,
    fontWeight: '600',
  },
  drawerFooterRow: {
    marginTop: 12,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: '#334155',
    alignItems: 'center',
  },
  drawerPrivacyLink: {
    paddingVertical: 6,
    paddingHorizontal: 10,
  },
  drawerPrivacyText: {
    color: '#38bdf8',
    fontSize: 12,
    fontWeight: '600',
    textDecorationLine: 'underline',
  },
  errorPrivacyBtn: {
    marginTop: 12,
    paddingVertical: 8,
    paddingHorizontal: 16,
  },
  errorPrivacyBtnText: {
    color: '#94a3b8',
    fontSize: 13,
    fontWeight: '500',
    textDecorationLine: 'underline',
  },
});
