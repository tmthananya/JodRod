/**
 * JodRod 3D Car Engine
 * Stylized low-poly 3D vehicle customization studio using Three.js.
 * Built to match the architecture, interaction, and retro aesthetics of Character3D.
 * Supports 8 distinct body styles, custom paint colors, wheel setups, and contextual cosmetic details.
 */
(function() {
  class Car3D {
    constructor(canvas, options = {}) {
      this.canvas = canvas;
      this.options = Object.assign({
        bodyStyle: 'pickup',   // 'sedan', 'pickup', 'coupe', 'suv', 'jeep', 'hatchback', 'van', 'supercar'
        paintColor: '#ea580c',  // Hex color
        wheelStyle: 'standard', // 'standard', 'sport', 'offroad' (also accepts legacy 'flood', 'steelie')
        detail: 'snorkel',     // 'none', 'spoiler', 'roofrack', 'snorkel', 'beacon'
        interactive: true,
        autoRotate: true
      }, options);

      this.scene = null;
      this.camera = null;
      this.renderer = null;
      this.carGroup = null;
      this.bodyMeshGroup = null;
      this.wheelsGroup = null;
      this.detailGroup = null;
      this.materials = {};
      this.animationFrameId = null;
      this.clock = new THREE.Clock();
      this.isDragging = false;
      this.prevMouseX = 0;
      this.rotationVelocity = 0;
      this.isFallbackMode = false;
      this.currentScale = 1.0;
      this.targetScale = 1.0;

      this.init();
    }

    init() {
      if (!window.THREE) {
        console.warn('Three.js is not loaded, falling back to 2D garage preview');
        this.renderFallback('3D Engine Not Available');
        return;
      }

      try {
        const rect = this.canvas.getBoundingClientRect();
        const width = rect.width || this.canvas.width || 340;
        const height = rect.height || this.canvas.height || 300;

        this.renderer = new THREE.WebGLRenderer({
          canvas: this.canvas,
          antialias: true,
          preserveDrawingBuffer: true,
          alpha: true
        });
        this.renderer.setSize(width, height, false);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

        this.scene = new THREE.Scene();

        // Camera positioned for an isometric/3/4 vehicle studio perspective
        this.camera = new THREE.PerspectiveCamera(34, width / height, 0.1, 100);
        this.camera.position.set(3.4, 2.2, 4.2);
        this.camera.lookAt(0, 0.42, 0);

        // Studio Lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.85);
        this.scene.add(ambientLight);

        const mainLight = new THREE.DirectionalLight(0xfff7ed, 0.95);
        mainLight.position.set(4, 5, 4);
        this.scene.add(mainLight);

        const rimLight = new THREE.DirectionalLight(0x38bdf8, 0.65);
        rimLight.position.set(-4, 3, -4);
        this.scene.add(rimLight);

        const fillLight = new THREE.DirectionalLight(0xfef08a, 0.35);
        fillLight.position.set(-2, -1, 3);
        this.scene.add(fillLight);

        // Showroom Turntable Pedestal
        const pedGeo = new THREE.CylinderGeometry(2.1, 2.2, 0.14, 36);
        const pedMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.65 });
        const pedestal = new THREE.Mesh(pedGeo, pedMat);
        pedestal.position.y = -0.07;
        this.scene.add(pedestal);

        // Hazard Glowing Turntable Ring
        const ringGeo = new THREE.TorusGeometry(2.05, 0.032, 16, 36);
        const ringMat = new THREE.MeshStandardMaterial({
          color: 0x0284c7,
          roughness: 0.2,
          emissive: 0x0284c7,
          emissiveIntensity: 0.35
        });
        const ring = new THREE.Mesh(ringGeo, ringMat);
        ring.rotation.x = Math.PI / 2;
        ring.position.y = 0;
        this.scene.add(ring);

        // Turntable inner safety grid
        const gridGeo = new THREE.CircleGeometry(1.95, 32);
        const gridMat = new THREE.MeshBasicMaterial({
          color: 0x0f172a,
          wireframe: false
        });
        const gridMesh = new THREE.Mesh(gridGeo, gridMat);
        gridMesh.rotation.x = -Math.PI / 2;
        gridMesh.position.y = 0.002;
        this.scene.add(gridMesh);

        // Car Root Group
        this.carGroup = new THREE.Group();
        this.carGroup.position.y = 0;
        this.carGroup.rotation.y = -0.45; // Hero angle
        this.scene.add(this.carGroup);

        this.initMaterials();
        this.rebuildCar();

        if (this.options.interactive) {
          this.bindEvents();
        }

        this.animate = this.animate.bind(this);
        this.animate();
      } catch (err) {
        console.error('WebGL initialization failed:', err);
        this.renderFallback('WebGL Studio Offline');
      }
    }

    renderFallback(msg) {
      this.isFallbackMode = true;
      const ctx = this.canvas.getContext('2d');
      if (!ctx) return;
      const w = this.canvas.width || 340;
      const h = this.canvas.height || 300;

      ctx.fillStyle = '#0f172a';
      ctx.fillRect(0, 0, w, h);

      // Pixel border
      ctx.strokeStyle = '#38bdf8';
      ctx.lineWidth = 4;
      ctx.strokeRect(6, 6, w - 12, h - 12);

      // Car silhouette
      ctx.fillStyle = this.options.paintColor || '#ea580c';
      ctx.fillRect(w / 2 - 70, h / 2 - 20, 140, 40);
      ctx.fillRect(w / 2 - 40, h / 2 - 45, 80, 25);

      // Wheels
      ctx.fillStyle = '#18181b';
      ctx.beginPath();
      ctx.arc(w / 2 - 45, h / 2 + 20, 14, 0, Math.PI * 2);
      ctx.arc(w / 2 + 45, h / 2 + 20, 14, 0, Math.PI * 2);
      ctx.fill();

      // Text
      ctx.fillStyle = '#f8fafc';
      ctx.font = '12px monospace';
      ctx.textAlign = 'center';
      ctx.fillText(msg, w / 2, h / 2 + 65);
      ctx.fillStyle = '#94a3b8';
      ctx.font = '10px monospace';
      ctx.fillText(`Style: ${this.options.bodyStyle} | Color: ${this.options.paintColor}`, w / 2, h / 2 + 82);
    }

    initMaterials() {
      this.materials.paint = new THREE.MeshStandardMaterial({
        color: new THREE.Color(this.options.paintColor),
        roughness: 0.35,
        metalness: 0.4
      });

      this.materials.darkChassis = new THREE.MeshStandardMaterial({
        color: 0x0f172a,
        roughness: 0.7
      });

      this.materials.bumper = new THREE.MeshStandardMaterial({
        color: 0x334155,
        roughness: 0.55,
        metalness: 0.2
      });

      this.materials.windowGlass = new THREE.MeshStandardMaterial({
        color: 0x1e293b,
        roughness: 0.12,
        metalness: 0.85
      });

      this.materials.headlights = new THREE.MeshStandardMaterial({
        color: 0xfef08a,
        emissive: 0xfef08a,
        emissiveIntensity: 0.7,
        roughness: 0.2
      });

      this.materials.taillights = new THREE.MeshStandardMaterial({
        color: 0xdc2626,
        emissive: 0xdc2626,
        emissiveIntensity: 0.75,
        roughness: 0.2
      });

      this.materials.tireRubber = new THREE.MeshStandardMaterial({
        color: 0x18181b,
        roughness: 0.92
      });

      this.materials.rimAlloy = new THREE.MeshStandardMaterial({
        color: 0xe2e8f0,
        roughness: 0.25,
        metalness: 0.75
      });

      this.materials.rimSteel = new THREE.MeshStandardMaterial({
        color: 0x27272a,
        roughness: 0.45,
        metalness: 0.45
      });

      this.materials.hazardOrange = new THREE.MeshStandardMaterial({
        color: 0xf97316,
        roughness: 0.3
      });

      this.materials.beaconBlue = new THREE.MeshStandardMaterial({
        color: 0x38bdf8,
        emissive: 0x0284c7,
        emissiveIntensity: 0.85,
        roughness: 0.15
      });

      this.materials.beaconAmber = new THREE.MeshStandardMaterial({
        color: 0xfbbf24,
        emissive: 0xd97706,
        emissiveIntensity: 0.85,
        roughness: 0.15
      });

      this.materials.chrome = new THREE.MeshStandardMaterial({
        color: 0xffffff,
        roughness: 0.1,
        metalness: 0.95
      });

      this.materials.carbon = new THREE.MeshStandardMaterial({
        color: 0x111827,
        roughness: 0.4,
        metalness: 0.5
      });
    }

    rebuildCar() {
      if (this.isFallbackMode || !this.carGroup) return;

      // Clear previous car parts
      if (this.bodyMeshGroup) this.carGroup.remove(this.bodyMeshGroup);
      if (this.wheelsGroup) this.carGroup.remove(this.wheelsGroup);
      if (this.detailGroup) this.carGroup.remove(this.detailGroup);

      this.bodyMeshGroup = new THREE.Group();
      this.wheelsGroup = new THREE.Group();
      this.detailGroup = new THREE.Group();

      this.carGroup.add(this.bodyMeshGroup);
      this.carGroup.add(this.wheelsGroup);
      this.carGroup.add(this.detailGroup);

      const style = this.normalizeBodyStyle(this.options.bodyStyle);
      const wheel = this.normalizeWheelStyle(this.options.wheelStyle);
      const detail = this.options.detail || 'none';

      // Vehicle scaling & centering so all body sizes fit comfortably in viewport
      const scaleMap = {
        sedan: 0.98,
        pickup: 0.93,
        coupe: 1.0,
        suv: 0.92,
        jeep: 0.94,
        hatchback: 1.02,
        van: 0.88,
        supercar: 1.02
      };
      this.targetScale = scaleMap[style] || 0.96;
      this.currentScale = this.targetScale * 0.92; // smooth pop-in transition
      this.carGroup.scale.set(this.currentScale, this.currentScale, this.currentScale);

      // Ground clearance based on vehicle type and wheel setup
      let groundClearance = 0.36;
      if (style === 'pickup' || style === 'suv' || style === 'jeep') {
        groundClearance = (wheel === 'offroad' || wheel === 'flood') ? 0.46 : 0.42;
      } else if (style === 'supercar') {
        groundClearance = 0.28;
      } else if (style === 'coupe') {
        groundClearance = 0.32;
      } else if (wheel === 'offroad' || wheel === 'flood') {
        groundClearance = 0.40;
      }

      this.bodyMeshGroup.position.y = groundClearance;
      this.detailGroup.position.y = groundClearance;

      // Build specific body style
      switch (style) {
        case 'sedan':
          this.buildSedan();
          break;
        case 'coupe':
          this.buildCoupe();
          break;
        case 'suv':
          this.buildSUV();
          break;
        case 'jeep':
          this.buildJeep();
          break;
        case 'hatchback':
        case 'compact':
          this.buildHatchback();
          break;
        case 'van':
          this.buildVan();
          break;
        case 'supercar':
          this.buildSupercar();
          break;
        case 'pickup':
        default:
          this.buildPickup();
          break;
      }

      // Build wheels
      this.buildWheels(wheel, groundClearance, style);

      // Build cosmetic details
      this.buildDetails(detail, style);
    }

    normalizeBodyStyle(style) {
      if (!style) return 'pickup';
      const s = String(style).toLowerCase();
      if (s === 'compact') return 'hatchback';
      if (['sedan', 'pickup', 'coupe', 'suv', 'jeep', 'hatchback', 'van', 'supercar'].includes(s)) {
        return s;
      }
      return 'pickup';
    }

    normalizeWheelStyle(wheel) {
      if (!wheel) return 'standard';
      const w = String(wheel).toLowerCase();
      if (w === 'sporty') return 'sport';
      if (w === 'flood') return 'offroad';
      if (w === 'steelie') return 'standard';
      return w;
    }

    // 1. SEDAN — Classic 3-box passenger car
    buildSedan() {
      const g = this.bodyMeshGroup;

      // Lower chassis base
      const chassisGeo = new THREE.BoxGeometry(1.22, 0.14, 2.45);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.07, 0);
      g.add(chassis);

      // Front Hood (Low & clean)
      const hoodGeo = new THREE.BoxGeometry(1.18, 0.28, 0.82);
      const hood = new THREE.Mesh(hoodGeo, this.materials.paint);
      hood.position.set(0, 0.26, 0.76);
      g.add(hood);

      // Front Bumper & Chrome Grille
      const fBumpGeo = new THREE.BoxGeometry(1.24, 0.14, 0.12);
      const fBump = new THREE.Mesh(fBumpGeo, this.materials.bumper);
      fBump.position.set(0, 0.12, 1.18);
      g.add(fBump);

      const grilleGeo = new THREE.BoxGeometry(0.85, 0.18, 0.04);
      const grille = new THREE.Mesh(grilleGeo, this.materials.chrome);
      grille.position.set(0, 0.24, 1.20);
      g.add(grille);

      // Sleek Headlights
      const hlGeo = new THREE.BoxGeometry(0.24, 0.12, 0.04);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.position.set(-0.46, 0.28, 1.18);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.position.set(0.46, 0.28, 1.18);
      g.add(hlL, hlR);

      // Passenger Cabin Base
      const cabBaseGeo = new THREE.BoxGeometry(1.18, 0.32, 1.12);
      const cabBase = new THREE.Mesh(cabBaseGeo, this.materials.paint);
      cabBase.position.set(0, 0.28, -0.15);
      g.add(cabBase);

      // Cabin Roof Greenhouse
      const cabTopGeo = new THREE.BoxGeometry(1.08, 0.38, 0.88);
      const cabTop = new THREE.Mesh(cabTopGeo, this.materials.paint);
      cabTop.position.set(0, 0.61, -0.16);
      g.add(cabTop);

      // Angled Front Windshield
      const wsGeo = new THREE.BoxGeometry(1.02, 0.38, 0.04);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.34;
      ws.position.set(0, 0.58, 0.31);
      g.add(ws);

      // Angled Rear Window
      const rwGeo = new THREE.BoxGeometry(1.02, 0.36, 0.04);
      const rw = new THREE.Mesh(rwGeo, this.materials.windowGlass);
      rw.rotation.x = -0.32;
      rw.position.set(0, 0.58, -0.62);
      g.add(rw);

      // 4-Door Side Windows (Front & Rear pairs)
      const winGeo = new THREE.BoxGeometry(0.04, 0.28, 0.38);
      const winLF = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winLF.position.set(-0.55, 0.60, 0.04);
      const winLR = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winLR.position.set(-0.55, 0.60, -0.36);
      const winRF = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winRF.position.set(0.55, 0.60, 0.04);
      const winRR = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winRR.position.set(0.55, 0.60, -0.36);
      g.add(winLF, winLR, winRF, winRR);

      // Stepped Rear Trunk Deck (Notchback 3-box design)
      const trunkGeo = new THREE.BoxGeometry(1.16, 0.26, 0.52);
      const trunk = new THREE.Mesh(trunkGeo, this.materials.paint);
      trunk.position.set(0, 0.25, -0.96);
      g.add(trunk);

      // Rear Bumper & Horizontal Taillights
      const rBumpGeo = new THREE.BoxGeometry(1.24, 0.14, 0.12);
      const rBump = new THREE.Mesh(rBumpGeo, this.materials.bumper);
      rBump.position.set(0, 0.12, -1.24);
      g.add(rBump);

      const tlGeo = new THREE.BoxGeometry(0.24, 0.12, 0.04);
      const tlL = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlL.position.set(-0.46, 0.28, -1.23);
      const tlR = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlR.position.set(0.46, 0.28, -1.23);
      g.add(tlL, tlR);
    }

    // 2. PICKUP TRUCK — Rugged cab with visible open cargo bed
    buildPickup() {
      const g = this.bodyMeshGroup;

      // Lower Chassis Base
      const chassisGeo = new THREE.BoxGeometry(1.24, 0.16, 2.5);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.08, 0);
      g.add(chassis);

      // Engine Hood
      const hoodGeo = new THREE.BoxGeometry(1.22, 0.38, 0.85);
      const hood = new THREE.Mesh(hoodGeo, this.materials.paint);
      hood.position.set(0, 0.32, 0.78);
      g.add(hood);

      // Front Bullbar & Grille
      const grilleGeo = new THREE.BoxGeometry(1.0, 0.26, 0.08);
      const grille = new THREE.Mesh(grilleGeo, this.materials.bumper);
      grille.position.set(0, 0.3, 1.22);
      g.add(grille);

      const barGeo = new THREE.BoxGeometry(1.18, 0.12, 0.12);
      const bar = new THREE.Mesh(barGeo, this.materials.bumper);
      bar.position.set(0, 0.22, 1.26);
      g.add(bar);

      // Front & Rear Bumpers
      const fBumpGeo = new THREE.BoxGeometry(1.26, 0.16, 0.14);
      const fBump = new THREE.Mesh(fBumpGeo, this.materials.bumper);
      fBump.position.set(0, 0.12, 1.24);
      g.add(fBump);

      const rBumpGeo = new THREE.BoxGeometry(1.26, 0.16, 0.12);
      const rBump = new THREE.Mesh(rBumpGeo, this.materials.bumper);
      rBump.position.set(0, 0.12, -1.26);
      g.add(rBump);

      // Headlights
      const hlGeo = new THREE.BoxGeometry(0.24, 0.16, 0.04);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.position.set(-0.46, 0.34, 1.22);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.position.set(0.46, 0.34, 1.22);
      g.add(hlL, hlR);

      // Passenger Cab
      const cabBaseGeo = new THREE.BoxGeometry(1.22, 0.38, 0.88);
      const cabBase = new THREE.Mesh(cabBaseGeo, this.materials.paint);
      cabBase.position.set(0, 0.32, -0.06);
      g.add(cabBase);

      const cabTopGeo = new THREE.BoxGeometry(1.14, 0.44, 0.76);
      const cabTop = new THREE.Mesh(cabTopGeo, this.materials.paint);
      cabTop.position.set(0, 0.71, -0.08);
      g.add(cabTop);

      // Windshield & Windows
      const wsGeo = new THREE.BoxGeometry(1.04, 0.34, 0.05);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.26;
      ws.position.set(0, 0.70, 0.32);
      g.add(ws);

      const backWinGeo = new THREE.BoxGeometry(0.96, 0.3, 0.04);
      const backWin = new THREE.Mesh(backWinGeo, this.materials.windowGlass);
      backWin.position.set(0, 0.72, -0.47);
      g.add(backWin);

      const sideWinGeo = new THREE.BoxGeometry(0.04, 0.3, 0.6);
      const swL = new THREE.Mesh(sideWinGeo, this.materials.windowGlass);
      swL.position.set(-0.58, 0.71, -0.08);
      const swR = new THREE.Mesh(sideWinGeo, this.materials.windowGlass);
      swR.position.set(0.58, 0.71, -0.08);
      g.add(swL, swR);

      // Open Cargo Bed (Bed floor & side walls)
      const bedFloorGeo = new THREE.BoxGeometry(1.06, 0.06, 0.86);
      const bedFloor = new THREE.Mesh(bedFloorGeo, this.materials.darkChassis);
      bedFloor.position.set(0, 0.16, -0.80);
      g.add(bedFloor);

      const wallGeo = new THREE.BoxGeometry(0.08, 0.34, 0.86);
      const wallL = new THREE.Mesh(wallGeo, this.materials.paint);
      wallL.position.set(-0.57, 0.33, -0.80);
      const wallR = new THREE.Mesh(wallGeo, this.materials.paint);
      wallR.position.set(0.57, 0.33, -0.80);
      g.add(wallL, wallR);

      // Tailgate
      const tgGeo = new THREE.BoxGeometry(1.22, 0.34, 0.08);
      const tg = new THREE.Mesh(tgGeo, this.materials.paint);
      tg.position.set(0, 0.33, -1.24);
      g.add(tg);

      // Taillights
      const tlGeo = new THREE.BoxGeometry(0.12, 0.22, 0.04);
      const tlL = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlL.position.set(-0.52, 0.36, -1.26);
      const tlR = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlR.position.set(0.52, 0.36, -1.26);
      g.add(tlL, tlR);
    }

    // 3. SPORT COUPE — Low, sleek sporty body with aggressive stance
    buildCoupe() {
      const g = this.bodyMeshGroup;

      // Low Chassis
      const chassisGeo = new THREE.BoxGeometry(1.22, 0.12, 2.4);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.06, 0);
      g.add(chassis);

      // Long Low Hood
      const hoodGeo = new THREE.BoxGeometry(1.2, 0.24, 0.95);
      const hood = new THREE.Mesh(hoodGeo, this.materials.paint);
      hood.position.set(0, 0.22, 0.74);
      g.add(hood);

      // Front Aerodynamic Splitter Lip
      const lipGeo = new THREE.BoxGeometry(1.24, 0.06, 0.14);
      const lip = new THREE.Mesh(lipGeo, this.materials.carbon);
      lip.position.set(0, 0.06, 1.22);
      g.add(lip);

      // Aggressive Angular Headlights
      const hlGeo = new THREE.BoxGeometry(0.26, 0.10, 0.04);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.rotation.z = -0.15;
      hlL.position.set(-0.46, 0.24, 1.21);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.rotation.z = 0.15;
      hlR.position.set(0.46, 0.24, 1.21);
      g.add(hlL, hlR);

      // 2-Door Sleek Cabin
      const cabBaseGeo = new THREE.BoxGeometry(1.18, 0.26, 0.88);
      const cabBase = new THREE.Mesh(cabBaseGeo, this.materials.paint);
      cabBase.position.set(0, 0.24, -0.16);
      g.add(cabBase);

      const cabTopGeo = new THREE.BoxGeometry(1.04, 0.34, 0.78);
      const cabTop = new THREE.Mesh(cabTopGeo, this.materials.paint);
      cabTop.position.set(0, 0.52, -0.18);
      g.add(cabTop);

      // Raked Fastback Windshield
      const wsGeo = new THREE.BoxGeometry(0.98, 0.40, 0.04);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.46;
      ws.position.set(0, 0.49, 0.25);
      g.add(ws);

      // Sloping Fastback Rear Glass
      const rwGeo = new THREE.BoxGeometry(0.98, 0.46, 0.04);
      const rw = new THREE.Mesh(rwGeo, this.materials.windowGlass);
      rw.rotation.x = -0.46;
      rw.position.set(0, 0.47, -0.62);
      g.add(rw);

      // Frameless Coupe Windows
      const swGeo = new THREE.BoxGeometry(0.04, 0.24, 0.65);
      const swL = new THREE.Mesh(swGeo, this.materials.windowGlass);
      swL.position.set(-0.53, 0.51, -0.18);
      const swR = new THREE.Mesh(swGeo, this.materials.windowGlass);
      swR.position.set(0.53, 0.51, -0.18);
      g.add(swL, swR);

      // Compact Rear Sport Deck & Bumper
      const rearDeckGeo = new THREE.BoxGeometry(1.16, 0.22, 0.45);
      const rearDeck = new THREE.Mesh(rearDeckGeo, this.materials.paint);
      rearDeck.position.set(0, 0.22, -0.96);
      g.add(rearDeck);

      // Horizontal LED Tail Strip
      const tlGeo = new THREE.BoxGeometry(1.0, 0.08, 0.04);
      const tl = new THREE.Mesh(tlGeo, this.materials.taillights);
      tl.position.set(0, 0.24, -1.20);
      g.add(tl);

      // Dual Sport Exhaust Tips
      const exhGeo = new THREE.CylinderGeometry(0.04, 0.04, 0.14, 12);
      const exhL = new THREE.Mesh(exhGeo, this.materials.chrome);
      exhL.rotation.x = Math.PI / 2;
      exhL.position.set(-0.35, 0.10, -1.23);
      const exhR = new THREE.Mesh(exhGeo, this.materials.chrome);
      exhR.rotation.x = Math.PI / 2;
      exhR.position.set(0.35, 0.10, -1.23);
      g.add(exhL, exhR);
    }

    // 4. SUV — Larger family vehicle with taller body & roof rails
    buildSUV() {
      const g = this.bodyMeshGroup;

      // Chassis
      const chassisGeo = new THREE.BoxGeometry(1.28, 0.16, 2.5);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.08, 0);
      g.add(chassis);

      // High Hood
      const hoodGeo = new THREE.BoxGeometry(1.24, 0.38, 0.85);
      const hood = new THREE.Mesh(hoodGeo, this.materials.paint);
      hood.position.set(0, 0.32, 0.78);
      g.add(hood);

      // Front & Rear Heavy Bumpers
      const fBumpGeo = new THREE.BoxGeometry(1.28, 0.18, 0.14);
      const fBump = new THREE.Mesh(fBumpGeo, this.materials.bumper);
      fBump.position.set(0, 0.14, 1.24);
      g.add(fBump);

      const rBumpGeo = new THREE.BoxGeometry(1.28, 0.18, 0.14);
      const rBump = new THREE.Mesh(rBumpGeo, this.materials.bumper);
      rBump.position.set(0, 0.14, -1.26);
      g.add(rBump);

      // Grille & Headlights
      const grilleGeo = new THREE.BoxGeometry(0.9, 0.24, 0.06);
      const grille = new THREE.Mesh(grilleGeo, this.materials.chrome);
      grille.position.set(0, 0.32, 1.22);
      g.add(grille);

      const hlGeo = new THREE.BoxGeometry(0.24, 0.16, 0.04);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.position.set(-0.48, 0.34, 1.22);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.position.set(0.48, 0.34, 1.22);
      g.add(hlL, hlR);

      // Full Enclosed Cabin
      const cabBaseGeo = new THREE.BoxGeometry(1.24, 0.38, 1.55);
      const cabBase = new THREE.Mesh(cabBaseGeo, this.materials.paint);
      cabBase.position.set(0, 0.32, -0.42);
      g.add(cabBase);

      const cabTopGeo = new THREE.BoxGeometry(1.16, 0.48, 1.48);
      const cabTop = new THREE.Mesh(cabTopGeo, this.materials.paint);
      cabTop.position.set(0, 0.73, -0.42);
      g.add(cabTop);

      // Windshield
      const wsGeo = new THREE.BoxGeometry(1.08, 0.40, 0.04);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.28;
      ws.position.set(0, 0.70, 0.34);
      g.add(ws);

      // Side Windows (3 panels per side)
      const winGeo = new THREE.BoxGeometry(0.04, 0.32, 0.42);
      const winL1 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winL1.position.set(-0.59, 0.73, 0.08);
      const winL2 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winL2.position.set(-0.59, 0.73, -0.40);
      const winL3 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winL3.position.set(-0.59, 0.73, -0.85);

      const winR1 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winR1.position.set(0.59, 0.73, 0.08);
      const winR2 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winR2.position.set(0.59, 0.73, -0.40);
      const winR3 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winR3.position.set(0.59, 0.73, -0.85);
      g.add(winL1, winL2, winL3, winR1, winR2, winR3);

      // Tailgate Rear Window
      const rwGeo = new THREE.BoxGeometry(0.98, 0.34, 0.04);
      const rw = new THREE.Mesh(rwGeo, this.materials.windowGlass);
      rw.position.set(0, 0.73, -1.18);
      g.add(rw);

      // Roof Rails
      const railGeo = new THREE.BoxGeometry(0.04, 0.06, 1.35);
      const railL = new THREE.Mesh(railGeo, this.materials.carbon);
      railL.position.set(-0.46, 0.99, -0.42);
      const railR = new THREE.Mesh(railGeo, this.materials.carbon);
      railR.position.set(0.46, 0.99, -0.42);
      g.add(railL, railR);

      // Taillights
      const tlGeo = new THREE.BoxGeometry(0.14, 0.28, 0.04);
      const tlL = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlL.position.set(-0.52, 0.42, -1.22);
      const tlR = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlR.position.set(0.52, 0.42, -1.22);
      g.add(tlL, tlR);
    }

    // 5. JEEP / OFF-ROAD — Rugged body, high clearance, exposed roll-cage & rear spare tire
    buildJeep() {
      const g = this.bodyMeshGroup;

      // Heavy Off-road Frame
      const chassisGeo = new THREE.BoxGeometry(1.24, 0.16, 2.3);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.08, 0);
      g.add(chassis);

      // Squared Rugged Hood
      const hoodGeo = new THREE.BoxGeometry(1.14, 0.34, 0.78);
      const hood = new THREE.Mesh(hoodGeo, this.materials.paint);
      hood.position.set(0, 0.32, 0.72);
      g.add(hood);

      // Front Winch Bumper
      const fBumpGeo = new THREE.BoxGeometry(1.26, 0.18, 0.16);
      const fBump = new THREE.Mesh(fBumpGeo, this.materials.bumper);
      fBump.position.set(0, 0.14, 1.18);
      g.add(fBump);

      // Center Recovery Winch
      const winchGeo = new THREE.CylinderGeometry(0.08, 0.08, 0.24, 12);
      const winch = new THREE.Mesh(winchGeo, this.materials.chrome);
      winch.rotation.z = Math.PI / 2;
      winch.position.set(0, 0.24, 1.20);
      g.add(winch);

      // Round Classic Headlights
      const hlGeo = new THREE.CylinderGeometry(0.11, 0.11, 0.04, 16);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.rotation.x = Math.PI / 2;
      hlL.position.set(-0.38, 0.34, 1.12);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.rotation.x = Math.PI / 2;
      hlR.position.set(0.38, 0.34, 1.12);
      g.add(hlL, hlR);

      // Open-Tub Adventure Cabin
      const tubGeo = new THREE.BoxGeometry(1.18, 0.36, 1.35);
      const tub = new THREE.Mesh(tubGeo, this.materials.paint);
      tub.position.set(0, 0.30, -0.32);
      g.add(tub);

      // Upright Windshield
      const wsFrameGeo = new THREE.BoxGeometry(1.12, 0.42, 0.06);
      const wsFrame = new THREE.Mesh(wsFrameGeo, this.materials.paint);
      wsFrame.rotation.x = 0.12;
      wsFrame.position.set(0, 0.66, 0.32);
      g.add(wsFrame);

      const wsGeo = new THREE.BoxGeometry(0.96, 0.34, 0.04);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.12;
      ws.position.set(0, 0.66, 0.34);
      g.add(ws);

      // Tubular Steel Roll-Cage (B-pillar hoop, C-pillar hoop, top connects)
      const cageMat = this.materials.darkChassis;

      const hoopBGeo = new THREE.BoxGeometry(1.10, 0.48, 0.08);
      const hoopB = new THREE.Mesh(hoopBGeo, cageMat);
      hoopB.position.set(0, 0.68, -0.05);
      g.add(hoopB);

      const hoopCGeo = new THREE.BoxGeometry(1.10, 0.48, 0.08);
      const hoopC = new THREE.Mesh(hoopCGeo, cageMat);
      hoopC.position.set(0, 0.68, -0.85);
      g.add(hoopC);

      const barLGeo = new THREE.BoxGeometry(0.08, 0.08, 0.88);
      const barL = new THREE.Mesh(barLGeo, cageMat);
      barL.position.set(-0.51, 0.90, -0.45);
      const barR = new THREE.Mesh(barLGeo, cageMat);
      barR.position.set(0.51, 0.90, -0.45);
      g.add(barL, barR);

      // Full-size Rear-Mounted Spare Wheel on Tailgate
      const spareTireGeo = new THREE.CylinderGeometry(0.32, 0.32, 0.16, 20);
      const spareTire = new THREE.Mesh(spareTireGeo, this.materials.tireRubber);
      spareTire.rotation.x = Math.PI / 2;
      spareTire.position.set(0, 0.38, -1.08);
      g.add(spareTire);

      const spareRimGeo = new THREE.CylinderGeometry(0.18, 0.18, 0.17, 14);
      const spareRim = new THREE.Mesh(spareRimGeo, this.materials.rimSteel);
      spareRim.rotation.x = Math.PI / 2;
      spareRim.position.set(0, 0.38, -1.08);
      g.add(spareRim);

      // Rear Bumper & Small Taillights
      const rBumpGeo = new THREE.BoxGeometry(1.24, 0.14, 0.12);
      const rBump = new THREE.Mesh(rBumpGeo, this.materials.bumper);
      rBump.position.set(0, 0.12, -1.02);
      g.add(rBump);

      const tlGeo = new THREE.BoxGeometry(0.14, 0.14, 0.04);
      const tlL = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlL.position.set(-0.48, 0.30, -1.01);
      const tlR = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlR.position.set(0.48, 0.30, -1.01);
      g.add(tlL, tlR);
    }

    // 6. HATCHBACK — Compact city car with short rear & sloping hatch
    buildHatchback() {
      const g = this.bodyMeshGroup;

      // Lower Chassis
      const chassisGeo = new THREE.BoxGeometry(1.2, 0.14, 2.25);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.07, 0);
      g.add(chassis);

      // Compact Aerodynamic Hood
      const hoodGeo = new THREE.BoxGeometry(1.16, 0.30, 0.72);
      const hood = new THREE.Mesh(hoodGeo, this.materials.paint);
      hood.position.set(0, 0.26, 0.70);
      g.add(hood);

      // Front & Rear Bumpers
      const fBumpGeo = new THREE.BoxGeometry(1.22, 0.16, 0.12);
      const fBump = new THREE.Mesh(fBumpGeo, this.materials.bumper);
      fBump.position.set(0, 0.12, 1.10);
      g.add(fBump);

      const rBumpGeo = new THREE.BoxGeometry(1.22, 0.16, 0.12);
      const rBump = new THREE.Mesh(rBumpGeo, this.materials.bumper);
      rBump.position.set(0, 0.12, -1.10);
      g.add(rBump);

      // Headlights
      const hlGeo = new THREE.BoxGeometry(0.22, 0.14, 0.04);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.position.set(-0.44, 0.28, 1.08);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.position.set(0.44, 0.28, 1.08);
      g.add(hlL, hlR);

      // Cabin Base & Sloped Roof
      const cabBaseGeo = new THREE.BoxGeometry(1.18, 0.32, 1.42);
      const cabBase = new THREE.Mesh(cabBaseGeo, this.materials.paint);
      cabBase.position.set(0, 0.28, -0.32);
      g.add(cabBase);

      const cabTopGeo = new THREE.BoxGeometry(1.08, 0.42, 1.20);
      const cabTop = new THREE.Mesh(cabTopGeo, this.materials.paint);
      cabTop.position.set(0, 0.62, -0.30);
      g.add(cabTop);

      // Windshield
      const wsGeo = new THREE.BoxGeometry(0.98, 0.38, 0.04);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.38;
      ws.position.set(0, 0.58, 0.32);
      g.add(ws);

      // Sloped Hatchback Rear Glass
      const rwGeo = new THREE.BoxGeometry(0.96, 0.36, 0.04);
      const rw = new THREE.Mesh(rwGeo, this.materials.windowGlass);
      rw.rotation.x = -0.40;
      rw.position.set(0, 0.57, -0.92);
      g.add(rw);

      // Side Windows
      const sideGlassGeo = new THREE.BoxGeometry(0.04, 0.30, 1.08);
      const sgL = new THREE.Mesh(sideGlassGeo, this.materials.windowGlass);
      sgL.position.set(-0.55, 0.62, -0.30);
      const sgR = new THREE.Mesh(sideGlassGeo, this.materials.windowGlass);
      sgR.position.set(0.55, 0.62, -0.30);
      g.add(sgL, sgR);

      // Taillights
      const tlGeo = new THREE.BoxGeometry(0.20, 0.16, 0.04);
      const tlL = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlL.position.set(-0.45, 0.34, -1.06);
      const tlR = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlR.position.set(0.45, 0.34, -1.06);
      g.add(tlL, tlR);
    }

    // 7. VAN / MPV — Spacious, boxier people carrier with multi-row windows
    buildVan() {
      const g = this.bodyMeshGroup;

      // Long Chassis
      const chassisGeo = new THREE.BoxGeometry(1.30, 0.16, 2.75);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.08, 0);
      g.add(chassis);

      // Tall Boxy Main Van Shell
      const bodyGeo = new THREE.BoxGeometry(1.26, 0.88, 2.3);
      const body = new THREE.Mesh(bodyGeo, this.materials.paint);
      body.position.set(0, 0.52, -0.16);
      g.add(body);

      // Short Snub Front Nose
      const noseGeo = new THREE.BoxGeometry(1.26, 0.36, 0.48);
      const nose = new THREE.Mesh(noseGeo, this.materials.paint);
      nose.position.set(0, 0.26, 1.18);
      g.add(nose);

      // Front & Rear Heavy Bumpers
      const fBumpGeo = new THREE.BoxGeometry(1.30, 0.18, 0.14);
      const fBump = new THREE.Mesh(fBumpGeo, this.materials.bumper);
      fBump.position.set(0, 0.14, 1.44);
      g.add(fBump);

      const rBumpGeo = new THREE.BoxGeometry(1.30, 0.18, 0.12);
      const rBump = new THREE.Mesh(rBumpGeo, this.materials.bumper);
      rBump.position.set(0, 0.14, -1.34);
      g.add(rBump);

      // Headlights
      const hlGeo = new THREE.BoxGeometry(0.24, 0.18, 0.04);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.position.set(-0.48, 0.34, 1.44);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.position.set(0.48, 0.34, 1.44);
      g.add(hlL, hlR);

      // Steep Windshield
      const wsGeo = new THREE.BoxGeometry(1.14, 0.48, 0.04);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.22;
      ws.position.set(0, 0.76, 1.08);
      g.add(ws);

      // 3-Row Split Side Windows
      const winGeo = new THREE.BoxGeometry(0.04, 0.34, 0.62);
      const winL1 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winL1.position.set(-0.64, 0.74, 0.55);
      const winL2 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winL2.position.set(-0.64, 0.74, -0.15);
      const winL3 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winL3.position.set(-0.64, 0.74, -0.80);

      const winR1 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winR1.position.set(0.64, 0.74, 0.55);
      const winR2 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winR2.position.set(0.64, 0.74, -0.15);
      const winR3 = new THREE.Mesh(winGeo, this.materials.windowGlass);
      winR3.position.set(0.64, 0.74, -0.80);
      g.add(winL1, winL2, winL3, winR1, winR2, winR3);

      // Twin Rear Cargo Door Windows
      const rwGeo = new THREE.BoxGeometry(0.46, 0.36, 0.04);
      const rwL = new THREE.Mesh(rwGeo, this.materials.windowGlass);
      rwL.position.set(-0.28, 0.74, -1.32);
      const rwR = new THREE.Mesh(rwGeo, this.materials.windowGlass);
      rwR.position.set(0.28, 0.74, -1.32);
      g.add(rwL, rwR);

      // Tall Vertical Taillights
      const tlGeo = new THREE.BoxGeometry(0.10, 0.40, 0.04);
      const tlL = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlL.position.set(-0.56, 0.52, -1.32);
      const tlR = new THREE.Mesh(tlGeo, this.materials.taillights);
      tlR.position.set(0.56, 0.52, -1.32);
      g.add(tlL, tlR);
    }

    // 8. SUPERCAR — Ultra-low exotic sports car with dramatic wedge silhouette
    buildSupercar() {
      const g = this.bodyMeshGroup;

      // Ultra-low Chassis
      const chassisGeo = new THREE.BoxGeometry(1.28, 0.09, 2.5);
      const chassis = new THREE.Mesh(chassisGeo, this.materials.darkChassis);
      chassis.position.set(0, 0.04, 0);
      g.add(chassis);

      // Sharp Aerodynamic Wedge Nose
      const noseGeo = new THREE.BoxGeometry(1.22, 0.18, 0.95);
      const nose = new THREE.Mesh(noseGeo, this.materials.paint);
      nose.position.set(0, 0.15, 0.75);
      g.add(nose);

      // Carbon Fiber Front Splitter Lip
      const lipGeo = new THREE.BoxGeometry(1.28, 0.04, 0.20);
      const lip = new THREE.Mesh(lipGeo, this.materials.carbon);
      lip.position.set(0, 0.04, 1.25);
      g.add(lip);

      // Slim Futuristic LED Headlights
      const hlGeo = new THREE.BoxGeometry(0.28, 0.06, 0.04);
      const hlL = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlL.rotation.z = -0.25;
      hlL.position.set(-0.46, 0.20, 1.20);
      const hlR = new THREE.Mesh(hlGeo, this.materials.headlights);
      hlR.rotation.z = 0.25;
      hlR.position.set(0.46, 0.20, 1.20);
      g.add(hlL, hlR);

      // Teardrop Cockpit Canopy
      const canopyBaseGeo = new THREE.BoxGeometry(1.12, 0.22, 0.85);
      const canopyBase = new THREE.Mesh(canopyBaseGeo, this.materials.paint);
      canopyBase.position.set(0, 0.20, -0.15);
      g.add(canopyBase);

      const roofGeo = new THREE.BoxGeometry(0.96, 0.26, 0.75);
      const roof = new THREE.Mesh(roofGeo, this.materials.carbon);
      roof.position.set(0, 0.42, -0.15);
      g.add(roof);

      // Deep Curved Panoramic Windshield
      const wsGeo = new THREE.BoxGeometry(0.92, 0.42, 0.04);
      const ws = new THREE.Mesh(wsGeo, this.materials.windowGlass);
      ws.rotation.x = 0.54;
      ws.position.set(0, 0.38, 0.26);
      g.add(ws);

      // Side Air Intakes / Sidepod Scoops
      const scoopGeo = new THREE.BoxGeometry(0.08, 0.16, 0.42);
      const scoopL = new THREE.Mesh(scoopGeo, this.materials.carbon);
      scoopL.position.set(-0.62, 0.18, -0.32);
      const scoopR = new THREE.Mesh(scoopGeo, this.materials.carbon);
      scoopR.position.set(0.62, 0.18, -0.32);
      g.add(scoopL, scoopR);

      // Mid-Engine Louvered Rear Deck
      const deckGeo = new THREE.BoxGeometry(1.18, 0.18, 0.68);
      const deck = new THREE.Mesh(deckGeo, this.materials.paint);
      deck.position.set(0, 0.20, -0.88);
      g.add(deck);

      const louverGeo = new THREE.BoxGeometry(0.72, 0.03, 0.52);
      const louver = new THREE.Mesh(louverGeo, this.materials.carbon);
      louver.position.set(0, 0.30, -0.88);
      g.add(louver);

      // Rear Diffuser with 4 Aero Fins
      const diffGeo = new THREE.BoxGeometry(1.20, 0.08, 0.16);
      const diff = new THREE.Mesh(diffGeo, this.materials.carbon);
      diff.position.set(0, 0.08, -1.24);
      g.add(diff);

      for (let i = 0; i < 4; i++) {
        const finGeo = new THREE.BoxGeometry(0.02, 0.12, 0.14);
        const fin = new THREE.Mesh(finGeo, this.materials.carbon);
        fin.position.set(-0.45 + i * 0.30, 0.08, -1.24);
        g.add(fin);
      }

      // Neon LED Tail Lightbar
      const tlGeo = new THREE.BoxGeometry(1.12, 0.05, 0.04);
      const tl = new THREE.Mesh(tlGeo, this.materials.taillights);
      tl.position.set(0, 0.24, -1.24);
      g.add(tl);

      // Quad Titanium Center Exhaust
      const exhGeo = new THREE.CylinderGeometry(0.035, 0.035, 0.12, 10);
      for (let i = 0; i < 4; i++) {
        const exh = new THREE.Mesh(exhGeo, this.materials.chrome);
        exh.rotation.x = Math.PI / 2;
        exh.position.set(-0.12 + i * 0.08, 0.16, -1.26);
        g.add(exh);
      }
    }

    buildWheels(wheelStyle, groundClearance, bodyStyle) {
      const g = this.wheelsGroup;

      let radius = 0.31;
      let width = 0.18;
      let rimRadius = 0.16;
      let rimMat = this.materials.rimSteel;

      if (wheelStyle === 'offroad' || wheelStyle === 'flood') {
        radius = 0.36;
        width = 0.23;
        rimRadius = 0.18;
        rimMat = this.materials.rimSteel;
      } else if (wheelStyle === 'sport') {
        radius = (bodyStyle === 'supercar') ? 0.28 : 0.30;
        width = 0.19;
        rimRadius = 0.21;
        rimMat = this.materials.chrome;
      } else {
        // Standard
        radius = (bodyStyle === 'supercar') ? 0.28 : 0.31;
        width = 0.18;
        rimRadius = 0.16;
        rimMat = this.materials.rimAlloy;
      }

      // Wheel base stretch depending on vehicle style
      let wheelZ = 0.78;
      if (bodyStyle === 'van') wheelZ = 0.95;
      else if (bodyStyle === 'hatchback') wheelZ = 0.72;
      else if (bodyStyle === 'supercar' || bodyStyle === 'coupe') wheelZ = 0.82;

      let wheelX = 0.64;
      if (bodyStyle === 'van') wheelX = 0.67;
      else if (bodyStyle === 'supercar') wheelX = 0.66;
      else if (bodyStyle === 'hatchback') wheelX = 0.62;

      const wheelPos = [
        { x: -wheelX, y: radius, z: wheelZ },
        { x: wheelX, y: radius, z: wheelZ },
        { x: -wheelX, y: radius, z: -wheelZ },
        { x: wheelX, y: radius, z: -wheelZ }
      ];

      wheelPos.forEach((p, idx) => {
        const wheelObj = new THREE.Group();
        wheelObj.position.set(p.x, p.y, p.z);

        // Rubber tire
        const tireGeo = new THREE.CylinderGeometry(radius, radius, width, 24);
        const tire = new THREE.Mesh(tireGeo, this.materials.tireRubber);
        tire.rotation.z = Math.PI / 2;
        wheelObj.add(tire);

        // Off-road / Mud tread lugs
        if (wheelStyle === 'offroad' || wheelStyle === 'flood') {
          const lugCount = 8;
          for (let i = 0; i < lugCount; i++) {
            const angle = (i / lugCount) * Math.PI * 2;
            const lugGeo = new THREE.BoxGeometry(width + 0.02, 0.04, 0.05);
            const lug = new THREE.Mesh(lugGeo, this.materials.tireRubber);
            lug.position.set(0, Math.sin(angle) * (radius - 0.01), Math.cos(angle) * (radius - 0.01));
            wheelObj.add(lug);
          }
        }

        // Rim
        const rimGeo = new THREE.CylinderGeometry(rimRadius, rimRadius, width + 0.01, 16);
        const rim = new THREE.Mesh(rimGeo, rimMat);
        rim.rotation.z = Math.PI / 2;
        wheelObj.add(rim);

        // Center hub cap
        const hubGeo = new THREE.CylinderGeometry(rimRadius * 0.45, rimRadius * 0.45, width + 0.03, 12);
        const hub = new THREE.Mesh(hubGeo, this.materials.darkChassis);
        hub.rotation.z = Math.PI / 2;
        wheelObj.add(hub);

        // Heavy mudflaps behind rear wheels on offroad setups
        if (idx >= 2 && (wheelStyle === 'offroad' || wheelStyle === 'flood')) {
          const flapGeo = new THREE.BoxGeometry(0.04, 0.22, 0.16);
          const flap = new THREE.Mesh(flapGeo, this.materials.tireRubber);
          flap.position.set(p.x > 0 ? 0.02 : -0.02, 0.06, -0.24);
          wheelObj.add(flap);
        }

        g.add(wheelObj);
      });
    }

    buildDetails(detail, style) {
      const g = this.detailGroup;

      if (detail === 'spoiler') {
        // High-downforce GT wing or trunk spoiler
        const isSuper = style === 'supercar';
        const isCoupe = style === 'coupe';
        const isHatch = style === 'hatchback';
        const isSedan = style === 'sedan';

        let wingY = 0.58;
        let wingZ = -1.15;
        let wingWidth = 1.18;

        if (isSuper) {
          wingY = 0.52;
          wingZ = -1.22;
          wingWidth = 1.28;
        } else if (isHatch) {
          wingY = 0.88;
          wingZ = -0.96;
          wingWidth = 1.08;
        } else if (isSedan) {
          wingY = 0.46;
          wingZ = -1.18;
          wingWidth = 1.14;
        }

        // Twin Mounting Pylons
        const pylonGeo = new THREE.BoxGeometry(0.04, 0.22, 0.08);
        const pylonL = new THREE.Mesh(pylonGeo, this.materials.carbon);
        pylonL.position.set(-0.35, wingY - 0.08, wingZ);
        const pylonR = new THREE.Mesh(pylonGeo, this.materials.carbon);
        pylonR.position.set(0.35, wingY - 0.08, wingZ);
        g.add(pylonL, pylonR);

        // Aerofoil Blade
        const wingGeo = new THREE.BoxGeometry(wingWidth, 0.04, 0.24);
        const wing = new THREE.Mesh(wingGeo, this.materials.carbon);
        wing.position.set(0, wingY + 0.03, wingZ);
        g.add(wing);

        // Wing Endplates
        const epGeo = new THREE.BoxGeometry(0.03, 0.14, 0.28);
        const epL = new THREE.Mesh(epGeo, this.materials.carbon);
        epL.position.set(-wingWidth / 2, wingY + 0.04, wingZ);
        const epR = new THREE.Mesh(epGeo, this.materials.carbon);
        epR.position.set(wingWidth / 2, wingY + 0.04, wingZ);
        g.add(epL, epR);

      } else if (detail === 'snorkel') {
        // High-water intake snorkel mounted on right A-pillar
        const pipeGeo = new THREE.CylinderGeometry(0.035, 0.035, 0.60, 12);
        const pipe = new THREE.Mesh(pipeGeo, this.materials.bumper);
        pipe.position.set(0.64, 0.62, 0.42);

        const headGeo = new THREE.BoxGeometry(0.12, 0.1, 0.14);
        const head = new THREE.Mesh(headGeo, this.materials.darkChassis);
        head.position.set(0.64, 0.94, 0.44);

        g.add(pipe, head);

      } else if (detail === 'roofrack') {
        // Roof luggage/gear rack with 4 flood spotlights
        const isVan = style === 'van';
        const isSUV = style === 'suv';
        const isHatch = style === 'hatchback';
        const rackY = isVan ? 1.15 : (isSUV ? 1.08 : (isHatch ? 0.88 : 1.02));
        const rackZ = isVan ? 0.0 : (isSUV ? -0.35 : (isHatch ? -0.28 : -0.25));
        const rackLen = isVan ? 1.4 : 0.95;

        const rackGeo = new THREE.BoxGeometry(1.08, 0.1, rackLen);
        const rack = new THREE.Mesh(rackGeo, this.materials.bumper);
        rack.position.set(0, rackY, rackZ);
        g.add(rack);

        // 4 Floodlights on the rack front bar
        for (let i = 0; i < 4; i++) {
          const x = -0.38 + i * 0.25;
          const lightGeo = new THREE.BoxGeometry(0.14, 0.12, 0.08);
          const light = new THREE.Mesh(lightGeo, this.materials.headlights);
          light.position.set(x, rackY + 0.12, rackZ + rackLen / 2 + 0.04);
          g.add(light);
        }

      } else if (detail === 'beacon') {
        // Dual emergency flood warning strobe lightbar
        const isVan = style === 'van';
        const isSUV = style === 'suv';
        const isSuper = style === 'supercar';
        const isHatch = style === 'hatchback';
        const beaconY = isVan ? 1.14 : (isSUV ? 1.06 : (isSuper ? 0.62 : (isHatch ? 0.88 : 1.02)));
        const beaconZ = isVan ? 0.5 : (isSuper ? -0.15 : (isHatch ? -0.1 : 0.0));

        const baseGeo = new THREE.BoxGeometry(0.85, 0.06, 0.14);
        const base = new THREE.Mesh(baseGeo, this.materials.darkChassis);
        base.position.set(0, beaconY, beaconZ);

        const amberGeo = new THREE.BoxGeometry(0.36, 0.08, 0.12);
        const amberLight = new THREE.Mesh(amberGeo, this.materials.beaconAmber);
        amberLight.position.set(-0.2, beaconY + 0.06, beaconZ);

        const blueGeo = new THREE.BoxGeometry(0.36, 0.08, 0.12);
        const blueLight = new THREE.Mesh(blueGeo, this.materials.beaconBlue);
        blueLight.position.set(0.2, beaconY + 0.06, beaconZ);

        g.add(base, amberLight, blueLight);
      }
    }

    updateConfig(newOptions = {}) {
      if (this.isFallbackMode) {
        Object.assign(this.options, newOptions);
        this.renderFallback('2D Studio Mode Active');
        return;
      }

      let needsRebuild = false;

      if (newOptions.paintColor && newOptions.paintColor !== this.options.paintColor) {
        this.options.paintColor = newOptions.paintColor;
        if (this.materials.paint) {
          this.materials.paint.color.set(this.options.paintColor);
        }
      }

      if (newOptions.bodyStyle && newOptions.bodyStyle !== this.options.bodyStyle) {
        this.options.bodyStyle = newOptions.bodyStyle;
        needsRebuild = true;
      }

      if (newOptions.wheelStyle && newOptions.wheelStyle !== this.options.wheelStyle) {
        this.options.wheelStyle = newOptions.wheelStyle;
        needsRebuild = true;
      }

      if (newOptions.detail !== undefined && newOptions.detail !== this.options.detail) {
        this.options.detail = newOptions.detail;
        needsRebuild = true;
      }

      if (needsRebuild) {
        this.rebuildCar();
      }

      this.render();
    }

    resetAngle() {
      if (this.carGroup) {
        this.carGroup.rotation.y = -0.45; // default hero angle
        this.rotationVelocity = 0;
        this.render();
      }
    }

    toggleAutoRotate() {
      this.options.autoRotate = !this.options.autoRotate;
      this.rotationVelocity = 0;
      return this.options.autoRotate;
    }

    setAutoRotate(enabled) {
      this.options.autoRotate = Boolean(enabled);
      this.rotationVelocity = 0;
      return this.options.autoRotate;
    }

    resetToDefaults() {
      this.updateConfig({
        bodyStyle: 'pickup',
        paintColor: '#ea580c',
        wheelStyle: 'standard',
        detail: 'snorkel'
      });
      this.resetAngle();
    }

    resize() {
      if (!this.canvas || !this.renderer || !this.camera) return;
      const rect = this.canvas.getBoundingClientRect();
      const width = rect.width || this.canvas.width || 340;
      const height = rect.height || this.canvas.height || 300;

      this.camera.aspect = width / height;
      this.camera.updateProjectionMatrix();
      this.renderer.setSize(width, height, false);
      this.render();
    }

    bindEvents() {
      const getX = (e) => (e.touches && e.touches[0] ? e.touches[0].clientX : e.clientX);

      const onStart = (e) => {
        this.isDragging = true;
        this.prevMouseX = getX(e);
        this.rotationVelocity = 0;
      };

      const onMove = (e) => {
        if (!this.isDragging) return;
        const currentX = getX(e);
        const delta = currentX - this.prevMouseX;
        this.prevMouseX = currentX;
        this.carGroup.rotation.y += delta * 0.015;
        this.rotationVelocity = delta * 0.015;
      };

      const onEnd = () => {
        this.isDragging = false;
      };

      this.canvas.addEventListener('mousedown', onStart);
      window.addEventListener('mousemove', onMove);
      window.addEventListener('mouseup', onEnd);

      this.canvas.addEventListener('touchstart', onStart, { passive: true });
      window.addEventListener('touchmove', onMove, { passive: true });
      window.addEventListener('touchend', onEnd);

      // Responsive resize handling
      this._onResize = () => this.resize();
      window.addEventListener('resize', this._onResize);
      if (typeof ResizeObserver !== 'undefined' && this.canvas.parentElement) {
        this._resizeObserver = new ResizeObserver(() => this.resize());
        this._resizeObserver.observe(this.canvas.parentElement);
      }
    }

    animate() {
      this.animationFrameId = requestAnimationFrame(this.animate);

      const time = this.clock.getElapsedTime();

      // Smooth scale interpolation when switching body styles
      if (this.carGroup) {
        if (Math.abs(this.currentScale - this.targetScale) > 0.001) {
          this.currentScale += (this.targetScale - this.currentScale) * 0.16;
          this.carGroup.scale.set(this.currentScale, this.currentScale, this.currentScale);
        }

        // Subtle suspension idling vibration
        this.carGroup.position.y = Math.sin(time * 3.5) * 0.005;

        if (this.isDragging) {
          // Handled in onMove
        } else if (Math.abs(this.rotationVelocity) > 0.0005) {
          this.carGroup.rotation.y += this.rotationVelocity;
          this.rotationVelocity *= 0.92;
        } else if (this.options.autoRotate) {
          this.carGroup.rotation.y += 0.005;
        }
      }

      this.render();
    }

    render() {
      if (this.renderer && this.scene && this.camera) {
        this.renderer.render(this.scene, this.camera);
      }
    }

    getSnapshot(quality = 0.82) {
      if (this.isFallbackMode || !this.renderer) {
        return this.canvas.toDataURL('image/jpeg', quality);
      }
      const currentRot = this.carGroup.rotation.y;
      this.carGroup.rotation.y = -0.52; // Crisp 3/4 hero presentation angle
      this.render();
      const dataUrl = this.canvas.toDataURL('image/jpeg', quality);
      this.carGroup.rotation.y = currentRot;
      return dataUrl;
    }

    getConfig() {
      return {
        bodyStyle: this.options.bodyStyle,
        paintColor: this.options.paintColor,
        wheelStyle: this.options.wheelStyle,
        detail: this.options.detail
      };
    }

    destroy() {
      if (this.animationFrameId) {
        cancelAnimationFrame(this.animationFrameId);
      }
      if (this._onResize) {
        window.removeEventListener('resize', this._onResize);
      }
      if (this._resizeObserver) {
        this._resizeObserver.disconnect();
      }
      if (this.renderer) {
        this.renderer.dispose();
      }
    }
  }

  window.Car3D = Car3D;
})();
