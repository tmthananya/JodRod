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
const CURRENT_MAC_IP = '192.168.1.44';
const LOCALHOST_IP = '127.0.0.1';
const HOTSPOT_IP = '172.20.10.2';
const HOME_WIFI_IP = '192.168.70.187';

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
    setShowConfig(false);
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
  };

  // Render Web-compatible container on Desktop browsers (Expo Web)
  if (Platform.OS === 'web') {
    return (
      <SafeAreaView style={styles.safeArea}>
        <StatusBar barStyle="dark-content" backgroundColor="#ffffff" />
        <View style={styles.webHeader}>
          <Text style={styles.webTitle}>JodRod Flood Parking Platform</Text>
          <View style={styles.webActions}>
            <Text style={styles.webUrlText}>{currentUrl}</Text>
            <TouchableOpacity
              style={styles.openBrowserBtn}
              onPress={() => Linking.openURL(currentUrl)}
            >
              <Text style={styles.openBrowserBtnText}>Open in New Tab</Text>
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
      <StatusBar barStyle="dark-content" backgroundColor="#ffffff" />

      {/* Floating Settings Button (Discreet, bottom-right) */}
      {!hasError && (
        <TouchableOpacity
          style={styles.floatingSettingsBtn}
          onPress={() => setShowConfig(!showConfig)}
          activeOpacity={0.7}
        >
          <Text style={styles.floatingSettingsText}>{showConfig ? '✕' : '⚙'}</Text>
        </TouchableOpacity>
      )}

      {/* Collapsible Server Selector Drawer */}
      {showConfig && !hasError && (
        <View style={styles.configDrawer}>
          <View style={styles.drawerHeader}>
            <Text style={styles.configTitle}>Connected Server</Text>
            <TouchableOpacity onPress={() => setShowConfig(false)}>
              <Text style={styles.drawerCloseText}>Done</Text>
            </TouchableOpacity>
          </View>
          <Text style={styles.activeUrlText} numberOfLines={1}>{currentUrl}</Text>
          
          <View style={styles.presetRow}>
            <TouchableOpacity
              style={[styles.presetChip, currentUrl.includes(CURRENT_MAC_IP) && styles.presetChipActive]}
              onPress={() => handleSwitchUrl(`http://${CURRENT_MAC_IP}:${APP_PORT}${APP_ROUTE}`)}
            >
              <Text style={[styles.presetChipText, currentUrl.includes(CURRENT_MAC_IP) && styles.presetChipTextActive]}>
                Wi-Fi ({CURRENT_MAC_IP})
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.presetChip, currentUrl.includes(LOCALHOST_IP) && styles.presetChipActive]}
              onPress={() => handleSwitchUrl(`http://${LOCALHOST_IP}:${APP_PORT}${APP_ROUTE}`)}
            >
              <Text style={[styles.presetChipText, currentUrl.includes(LOCALHOST_IP) && styles.presetChipTextActive]}>
                Localhost ({LOCALHOST_IP})
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.presetChip, currentUrl.includes(HOTSPOT_IP) && styles.presetChipActive]}
              onPress={() => handleSwitchUrl(`http://${HOTSPOT_IP}:${APP_PORT}${APP_ROUTE}`)}
            >
              <Text style={[styles.presetChipText, currentUrl.includes(HOTSPOT_IP) && styles.presetChipTextActive]}>
                Hotspot ({HOTSPOT_IP})
              </Text>
            </TouchableOpacity>
          </View>

          <View style={styles.inputRow}>
            <TextInput
              style={styles.ipInput}
              placeholder="Custom IP:8088"
              placeholderTextColor="#94a3b8"
              value={customInput}
              onChangeText={setCustomInput}
              autoCapitalize="none"
              autoCorrect={false}
            />
            <TouchableOpacity style={styles.applyBtn} onPress={handleApplyCustom}>
              <Text style={styles.applyBtnText}>Connect</Text>
            </TouchableOpacity>
          </View>
        </View>
      )}

      {/* Error Recovery View */}
      {hasError ? (
        <View style={styles.errorContainer}>
          <View style={styles.errorCard}>
            <Text style={styles.errorTitle}>Connection Unavailable</Text>
            <Text style={styles.errorSubtitle}>
              Unable to reach JodRod server at:{'\n'}
              <Text style={styles.urlText}>{currentUrl}</Text>
            </Text>

            <View style={styles.helpBox}>
              <Text style={styles.helpHeader}>Troubleshooting</Text>
              <Text style={styles.helpText}>
                1. Ensure your iPhone and Mac are on the same Wi-Fi network.{'\n'}
                2. Verify that the JodRod PHP server is running on port {APP_PORT}.{'\n'}
                3. Select one of the verified network addresses below.
              </Text>
            </View>

            {errorMessage ? (
              <Text style={styles.errorDetail}>{errorMessage}</Text>
            ) : null}

            <View style={styles.presetGrid}>
              <TouchableOpacity
                style={styles.errorPresetBtn}
                onPress={() => handleSwitchUrl(`http://${CURRENT_MAC_IP}:${APP_PORT}${APP_ROUTE}`)}
              >
                <Text style={styles.errorPresetBtnText}>Switch to Wi-Fi ({CURRENT_MAC_IP}:{APP_PORT})</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.errorPresetBtn}
                onPress={() => handleSwitchUrl(`http://${LOCALHOST_IP}:${APP_PORT}${APP_ROUTE}`)}
              >
                <Text style={styles.errorPresetBtnText}>Switch to Localhost ({LOCALHOST_IP}:{APP_PORT})</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.errorPresetBtn}
                onPress={() => handleSwitchUrl(`http://${HOTSPOT_IP}:${APP_PORT}${APP_ROUTE}`)}
              >
                <Text style={styles.errorPresetBtnText}>Switch to Hotspot ({HOTSPOT_IP}:{APP_PORT})</Text>
              </TouchableOpacity>
            </View>

            <TouchableOpacity style={styles.retryButton} onPress={handleReload}>
              <Text style={styles.retryButtonText}>Retry Connection</Text>
            </TouchableOpacity>
          </View>
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
              <ActivityIndicator size="small" color="#0284c7" />
              <Text style={styles.loadingText}>Connecting to JodRod...</Text>
            </View>
          )}
          onNavigationStateChange={(navState) => {
            setCanGoBack(navState.canGoBack);
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
              setErrorMessage(`Server responded with HTTP ${nativeEvent.statusCode}`);
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
    backgroundColor: '#ffffff',
  },
  webview: {
    flex: 1,
    backgroundColor: '#ffffff',
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
    fontSize: 13,
    fontWeight: '600',
    letterSpacing: 0.3,
  },
  webActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  webUrlText: {
    color: '#94a3b8',
    fontSize: 12,
    fontFamily: Platform.OS === 'ios' ? 'Menlo' : 'monospace',
  },
  openBrowserBtn: {
    backgroundColor: '#0284c7',
    paddingVertical: 5,
    paddingHorizontal: 12,
    borderRadius: 6,
  },
  openBrowserBtnText: {
    color: '#ffffff',
    fontSize: 12,
    fontWeight: '600',
  },
  floatingSettingsBtn: {
    position: 'absolute',
    bottom: 20,
    right: 16,
    zIndex: 999,
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: 'rgba(15, 23, 42, 0.75)',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 4,
    elevation: 4,
  },
  floatingSettingsText: {
    color: '#ffffff',
    fontSize: 16,
  },
  configDrawer: {
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
    paddingHorizontal: 16,
    paddingVertical: 14,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.08,
    shadowRadius: 8,
    elevation: 6,
  },
  drawerHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  configTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#0f172a',
  },
  drawerCloseText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#0284c7',
  },
  activeUrlText: {
    fontSize: 11,
    color: '#64748b',
    marginBottom: 10,
    fontFamily: Platform.OS === 'ios' ? 'Menlo' : 'monospace',
  },
  presetRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginBottom: 10,
  },
  presetChip: {
    backgroundColor: '#f1f5f9',
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  presetChipActive: {
    backgroundColor: '#0284c7',
    borderColor: '#0284c7',
  },
  presetChipText: {
    color: '#334155',
    fontSize: 11,
    fontWeight: '600',
  },
  presetChipTextActive: {
    color: '#ffffff',
  },
  inputRow: {
    flexDirection: 'row',
    gap: 8,
  },
  ipInput: {
    flex: 1,
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#cbd5e1',
    borderRadius: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    color: '#0f172a',
    fontSize: 12,
  },
  applyBtn: {
    backgroundColor: '#0284c7',
    paddingHorizontal: 14,
    justifyContent: 'center',
    borderRadius: 6,
  },
  applyBtnText: {
    color: '#ffffff',
    fontSize: 12,
    fontWeight: '600',
  },
  loadingContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#ffffff',
  },
  loadingText: {
    marginTop: 12,
    color: '#64748b',
    fontSize: 13,
    fontWeight: '500',
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#f8fafc',
  },
  errorCard: {
    width: '100%',
    maxWidth: 420,
    backgroundColor: '#ffffff',
    borderRadius: 14,
    padding: 24,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.08,
    shadowRadius: 12,
    elevation: 4,
  },
  errorTitle: {
    color: '#0f172a',
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 6,
    textAlign: 'center',
  },
  errorSubtitle: {
    color: '#64748b',
    fontSize: 13,
    textAlign: 'center',
    marginBottom: 16,
    lineHeight: 18,
  },
  urlText: {
    color: '#0284c7',
    fontWeight: '600',
    fontFamily: Platform.OS === 'ios' ? 'Menlo' : 'monospace',
  },
  helpBox: {
    backgroundColor: '#f8fafc',
    padding: 12,
    borderRadius: 8,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  helpHeader: {
    color: '#0f172a',
    fontSize: 12,
    fontWeight: '700',
    marginBottom: 4,
  },
  helpText: {
    color: '#64748b',
    fontSize: 11,
    lineHeight: 16,
  },
  errorDetail: {
    color: '#dc2626',
    fontSize: 11,
    marginBottom: 14,
    textAlign: 'center',
  },
  presetGrid: {
    gap: 8,
    marginBottom: 16,
  },
  errorPresetBtn: {
    backgroundColor: '#f1f5f9',
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#cbd5e1',
    alignItems: 'center',
  },
  errorPresetBtnText: {
    color: '#0f172a',
    fontSize: 12,
    fontWeight: '600',
  },
  retryButton: {
    backgroundColor: '#0284c7',
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
  },
  retryButtonText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '600',
  },
});
