/**
 * JodRod 3D Character Engine
 * Stylized low-poly 3D game character builder using Three.js
 * Supports Gender selection, customizable Outfits, Hairstyles, and Color Palettes
 */
(function() {
  class Character3D {
    constructor(canvas, options = {}) {
      this.canvas = canvas;
      this.options = Object.assign({
        gender: 'female',
        feature: 'natural',
        hair: 'ponytail',
        hairColor: '#261e1b',
        clothes: 'vest',
        clothesColor: '#ea580c',
        skinColor: '#fed7aa',
        interactive: true,
        autoRotate: true
      }, options);

      this.scene = null;
      this.camera = null;
      this.renderer = null;
      this.charGroup = null;
      this.parts = {};
      this.materials = {};
      this.animationFrameId = null;
      this.clock = new THREE.Clock();
      this.isDragging = false;
      this.prevMouseX = 0;
      this.rotationVelocity = 0;

      this.init();
    }

    init() {
      if (!window.THREE) {
        console.error('Three.js is not loaded');
        return;
      }

      const rect = this.canvas.getBoundingClientRect();
      const width = rect.width || this.canvas.width || 340;
      const height = rect.height || this.canvas.height || 380;

      this.renderer = new THREE.WebGLRenderer({
        canvas: this.canvas,
        antialias: true,
        preserveDrawingBuffer: true,
        alpha: true
      });
      this.renderer.setSize(width, height, false);
      this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

      this.scene = new THREE.Scene();

      this.camera = new THREE.PerspectiveCamera(34, width / height, 0.1, 100);
      this.camera.position.set(0, 1.12, 4.65);
      this.camera.lookAt(0, 1.05, 0);

      // Lighting
      const ambientLight = new THREE.AmbientLight(0xffffff, 0.78);
      this.scene.add(ambientLight);

      const mainLight = new THREE.DirectionalLight(0xfff7ed, 0.85);
      mainLight.position.set(2.5, 4.5, 3.5);
      this.scene.add(mainLight);

      const rimLight = new THREE.DirectionalLight(0x38bdf8, 0.55);
      rimLight.position.set(-3, 2.5, -3);
      this.scene.add(rimLight);

      // Pedestal
      const pedGeo = new THREE.CylinderGeometry(0.95, 1.05, 0.12, 32);
      const pedMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.6 });
      const pedestal = new THREE.Mesh(pedGeo, pedMat);
      pedestal.position.y = -0.06;
      this.scene.add(pedestal);

      // Pedestal ring glow
      const ringGeo = new THREE.TorusGeometry(0.96, 0.025, 16, 32);
      const ringMat = new THREE.MeshStandardMaterial({ color: 0x0284c7, roughness: 0.2 });
      const ring = new THREE.Mesh(ringGeo, ringMat);
      ring.rotation.x = Math.PI / 2;
      ring.position.y = 0;
      this.scene.add(ring);

      this.charGroup = new THREE.Group();
      this.charGroup.position.y = 0;
      this.charGroup.rotation.y = 0.35; // friendly 3/4 hero pose
      this.scene.add(this.charGroup);

      this.initMaterials();
      this.buildBaseBody();
      this.buildHair(this.options.hair);
      this.buildClothes(this.options.clothes);
      this.buildFeature(this.options.feature);

      if (this.options.interactive) {
        this.bindEvents();
      }

      this.animate = this.animate.bind(this);
      this.animate();
    }

    initMaterials() {
      this.materials.skin = new THREE.MeshStandardMaterial({
        color: new THREE.Color(this.options.skinColor),
        roughness: 0.5
      });
      this.materials.hair = new THREE.MeshStandardMaterial({
        color: new THREE.Color(this.options.hairColor),
        roughness: 0.55
      });
      this.materials.clothes = new THREE.MeshStandardMaterial({
        color: new THREE.Color(this.options.clothesColor),
        roughness: 0.45
      });
      this.materials.dark = new THREE.MeshStandardMaterial({
        color: 0x1e293b,
        roughness: 0.6
      });
      this.materials.blush = new THREE.MeshStandardMaterial({
        color: 0xf472b6,
        roughness: 0.6
      });
      this.materials.white = new THREE.MeshStandardMaterial({
        color: 0xf8fafc,
        roughness: 0.3
      });
      this.materials.reflective = new THREE.MeshStandardMaterial({
        color: 0xe2e8f0,
        roughness: 0.25,
        metalness: 0.25
      });
      this.materials.brass = new THREE.MeshStandardMaterial({
        color: 0xf59e0b,
        roughness: 0.3,
        metalness: 0.5
      });
      this.materials.boots = new THREE.MeshStandardMaterial({
        color: 0xf59e0b, // iconic bright flood rescue boots
        roughness: 0.35
      });
      this.materials.stubble = new THREE.MeshStandardMaterial({
        color: 0x334155,
        roughness: 0.8,
        transparent: true,
        opacity: 0.7
      });
      this.materials.pink = new THREE.MeshStandardMaterial({
        color: 0xf43f5e,
        roughness: 0.4
      });
      this.materials.yellow = new THREE.MeshStandardMaterial({
        color: 0xfde047,
        roughness: 0.4
      });
    }

    buildBaseBody() {
      // Clear previous base body parts if re-building
      if (this.parts.baseGroup) {
        this.charGroup.remove(this.parts.baseGroup);
      }

      const bg = new THREE.Group();
      this.parts.baseGroup = bg;
      this.charGroup.add(bg);

      const isFem = this.options.gender === 'female';

      // Head
      const headGeo = new THREE.BoxGeometry(0.72, 0.72, 0.68);
      this.parts.head = new THREE.Mesh(headGeo, this.materials.skin);
      this.parts.head.position.y = 1.62;
      bg.add(this.parts.head);

      // Neck
      const neckGeo = new THREE.BoxGeometry(0.28, 0.16, 0.26);
      const neck = new THREE.Mesh(neckGeo, this.materials.skin);
      neck.position.set(0, 1.28, 0);
      bg.add(neck);

      // Eyes
      const eyeGeo = new THREE.BoxGeometry(0.09, 0.12, 0.05);
      const eyeL = new THREE.Mesh(eyeGeo, this.materials.dark);
      eyeL.position.set(-0.18, 1.64, 0.34);
      const eyeR = new THREE.Mesh(eyeGeo, this.materials.dark);
      eyeR.position.set(0.18, 1.64, 0.34);
      bg.add(eyeL, eyeR);

      // Eye highlights
      const hlGeo = new THREE.BoxGeometry(0.035, 0.045, 0.06);
      const hlL = new THREE.Mesh(hlGeo, this.materials.white);
      hlL.position.set(-0.16, 1.67, 0.35);
      const hlR = new THREE.Mesh(hlGeo, this.materials.white);
      hlR.position.set(0.20, 1.67, 0.35);
      bg.add(hlL, hlR);

      // Gender-specific facial features: Eyebrows, Eyelashes, and Jawline
      if (isFem) {
        // Delicate feminine eyelashes
        const lashGeo = new THREE.BoxGeometry(0.12, 0.032, 0.06);
        const lashL = new THREE.Mesh(lashGeo, this.materials.dark);
        lashL.position.set(-0.19, 1.72, 0.35);
        lashL.rotation.z = 0.12;
        const lashR = new THREE.Mesh(lashGeo, this.materials.dark);
        lashR.position.set(0.19, 1.72, 0.35);
        lashR.rotation.z = -0.12;
        bg.add(lashL, lashR);

        // Soft arched feminine eyebrows
        const browGeo = new THREE.BoxGeometry(0.11, 0.025, 0.05);
        const browL = new THREE.Mesh(browGeo, this.materials.hair);
        browL.position.set(-0.18, 1.75, 0.35);
        browL.rotation.z = 0.06;
        const browR = new THREE.Mesh(browGeo, this.materials.hair);
        browR.position.set(0.18, 1.75, 0.35);
        browR.rotation.z = -0.06;
        bg.add(browL, browR);
      } else {
        // Bold masculine eyebrows
        const browGeo = new THREE.BoxGeometry(0.14, 0.045, 0.05);
        const browL = new THREE.Mesh(browGeo, this.materials.hair);
        browL.position.set(-0.18, 1.74, 0.35);
        browL.rotation.z = -0.08;
        const browR = new THREE.Mesh(browGeo, this.materials.hair);
        browR.position.set(0.18, 1.74, 0.35);
        browR.rotation.z = 0.08;
        bg.add(browL, browR);

        // Stronger defined masculine jawline
        const jawGeo = new THREE.BoxGeometry(0.36, 0.07, 0.05);
        const jaw = new THREE.Mesh(jawGeo, this.materials.skin);
        jaw.position.set(0, 1.30, 0.34);
        bg.add(jaw);
      }

      // Cute blush cheeks
      const blushGeo = new THREE.BoxGeometry(isFem ? 0.14 : 0.11, 0.045, 0.04);
      const blushL = new THREE.Mesh(blushGeo, this.materials.blush);
      blushL.position.set(-0.24, 1.53, 0.34);
      const blushR = new THREE.Mesh(blushGeo, this.materials.blush);
      blushR.position.set(0.24, 1.53, 0.34);
      bg.add(blushL, blushR);

      // Smile mouth
      const mouthGeo = new THREE.BoxGeometry(isFem ? 0.13 : 0.15, 0.04, 0.04);
      const mouth = new THREE.Mesh(mouthGeo, this.materials.dark);
      mouth.position.set(0, 1.47, 0.34);
      bg.add(mouth);

      // Hands
      const handX = isFem ? 0.49 : 0.53;
      const handGeo = new THREE.BoxGeometry(0.15, 0.15, 0.18);
      this.parts.handL = new THREE.Mesh(handGeo, this.materials.skin);
      this.parts.handL.position.set(-handX, 0.54, 0);
      this.parts.handR = new THREE.Mesh(handGeo, this.materials.skin);
      this.parts.handR.position.set(handX, 0.54, 0);
      bg.add(this.parts.handL, this.parts.handR);

      // Legs / Pants
      const legW = isFem ? 0.22 : 0.25;
      const legSpacing = isFem ? 0.16 : 0.18;
      const legGeo = new THREE.BoxGeometry(legW, 0.60, 0.26);
      const legL = new THREE.Mesh(legGeo, this.materials.dark);
      legL.position.set(-legSpacing, 0.36, 0);
      const legR = new THREE.Mesh(legGeo, this.materials.dark);
      legR.position.set(legSpacing, 0.36, 0);
      bg.add(legL, legR);

      // Flood Boots (Iconic safety rubber boots with sculpted heel, sole, and calf shaft)
      const bootFootW = legW + 0.04;
      const bootFootGeo = new THREE.BoxGeometry(bootFootW, 0.14, 0.40);
      const bootHeelGeo = new THREE.BoxGeometry(bootFootW + 0.01, 0.05, 0.15);
      const bootSoleGeo = new THREE.BoxGeometry(bootFootW + 0.01, 0.03, 0.24);
      const bootShaftGeo = new THREE.BoxGeometry(legW + 0.03, 0.22, 0.29);

      // Left Boot (Foot, rear heel, front sole tread, upper shaft)
      const bootL = new THREE.Mesh(bootFootGeo, this.materials.boots);
      bootL.position.set(-legSpacing, 0.09, 0.04);
      const heelL = new THREE.Mesh(bootHeelGeo, this.materials.dark);
      heelL.position.set(-legSpacing, 0.025, -0.08);
      const soleL = new THREE.Mesh(bootSoleGeo, this.materials.dark);
      soleL.position.set(-legSpacing, 0.015, 0.11);
      const shaftL = new THREE.Mesh(bootShaftGeo, this.materials.boots);
      shaftL.position.set(-legSpacing, 0.19, 0.01);
      bg.add(bootL, heelL, soleL, shaftL);

      // Right Boot (Foot, rear heel, front sole tread, upper shaft)
      const bootR = new THREE.Mesh(bootFootGeo, this.materials.boots);
      bootR.position.set(legSpacing, 0.09, 0.04);
      const heelR = new THREE.Mesh(bootHeelGeo, this.materials.dark);
      heelR.position.set(legSpacing, 0.025, -0.08);
      const soleR = new THREE.Mesh(bootSoleGeo, this.materials.dark);
      soleR.position.set(legSpacing, 0.015, 0.11);
      const shaftR = new THREE.Mesh(bootShaftGeo, this.materials.boots);
      shaftR.position.set(legSpacing, 0.19, 0.01);
      bg.add(bootR, heelR, soleR, shaftR);
    }

    buildHair(style) {
      if (this.parts.hairGroup) {
        this.charGroup.remove(this.parts.hairGroup);
      }

      const hg = new THREE.Group();
      this.parts.hairGroup = hg;
      this.charGroup.add(hg);

      const mat = this.materials.hair;

      if (style === 'spiky') {
        const top = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.30, 0.72), mat);
        top.position.set(0, 1.86, -0.02);
        hg.add(top);

        const spikeGeo = new THREE.ConeGeometry(0.14, 0.32, 4);
        for (let i = -2; i <= 2; i++) {
          const spike = new THREE.Mesh(spikeGeo, mat);
          spike.position.set(i * 0.15, 2.06, (i % 2 === 0 ? 0.08 : -0.08));
          spike.rotation.z = -i * 0.16;
          hg.add(spike);
        }

        const back = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.45, 0.14), mat);
        back.position.set(0, 1.68, -0.34);
        const sideL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.35, 0.68), mat);
        sideL.position.set(-0.38, 1.72, 0);
        const sideR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.35, 0.68), mat);
        sideR.position.set(0.38, 1.72, 0);
        hg.add(back, sideL, sideR);
      } else if (style === 'short') {
        // Neat side-part
        const top = new THREE.Mesh(new THREE.BoxGeometry(0.78, 0.32, 0.74), mat);
        top.position.set(0, 1.86, -0.02);
        hg.add(top);

        const fringe = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.15, 0.15), mat);
        fringe.position.set(0, 1.86, 0.34);
        hg.add(fringe);

        const sideL = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.35, 0.65), mat);
        sideL.position.set(-0.38, 1.72, 0);
        const sideR = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.35, 0.65), mat);
        sideR.position.set(0.38, 1.72, 0);
        const back = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.45, 0.14), mat);
        back.position.set(0, 1.68, -0.34);
        hg.add(sideL, sideR, back);
      } else if (style === 'bob') {
        // Rounded bob cut with longer sides
        const top = new THREE.Mesh(new THREE.BoxGeometry(0.80, 0.34, 0.76), mat);
        top.position.set(0, 1.87, -0.01);
        hg.add(top);

        const sideL = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.60, 0.68), mat);
        sideL.position.set(-0.39, 1.58, 0.02);
        const sideR = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.60, 0.68), mat);
        sideR.position.set(0.39, 1.58, 0.02);
        hg.add(sideL, sideR);

        const back = new THREE.Mesh(new THREE.BoxGeometry(0.78, 0.60, 0.16), mat);
        back.position.set(0, 1.58, -0.34);
        hg.add(back);
      } else if (style === 'ponytail') {
        // Bob + High Ponytail
        const top = new THREE.Mesh(new THREE.BoxGeometry(0.78, 0.32, 0.74), mat);
        top.position.set(0, 1.86, -0.02);
        hg.add(top);

        const fringe = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.15, 0.14), mat);
        fringe.position.set(0, 1.86, 0.34);
        hg.add(fringe);

        const sideL = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.40, 0.68), mat);
        sideL.position.set(-0.38, 1.70, 0);
        const sideR = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.40, 0.68), mat);
        sideR.position.set(0.38, 1.70, 0);
        const back = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.50, 0.14), mat);
        back.position.set(0, 1.65, -0.34);
        hg.add(sideL, sideR, back);

        const tieGeo = new THREE.CylinderGeometry(0.08, 0.08, 0.08, 12);
        const tie = new THREE.Mesh(tieGeo, this.materials.clothes);
        tie.rotation.x = Math.PI / 2;
        tie.position.set(0, 1.95, -0.42);
        hg.add(tie);

        const tailGeo = new THREE.ConeGeometry(0.14, 0.48, 5);
        const tail = new THREE.Mesh(tailGeo, mat);
        tail.rotation.x = -Math.PI / 3;
        tail.position.set(0, 1.80, -0.62);
        hg.add(tail);
      } else if (style === 'helmet') {
        // Flood Rescue Hard Hat
        const helmetMat = new THREE.MeshStandardMaterial({ color: 0xfacc15, roughness: 0.25 }); // Safety yellow
        const domeGeo = new THREE.SphereGeometry(0.48, 16, 12, 0, Math.PI * 2, 0, Math.PI / 2);
        const dome = new THREE.Mesh(domeGeo, helmetMat);
        dome.position.set(0, 1.78, 0);
        hg.add(dome);

        const rimGeo = new THREE.CylinderGeometry(0.56, 0.56, 0.06, 24);
        const rim = new THREE.Mesh(rimGeo, helmetMat);
        rim.position.set(0, 1.78, 0.04);
        hg.add(rim);

        // Front Headlamp badge
        const lampGeo = new THREE.BoxGeometry(0.14, 0.10, 0.08);
        const lamp = new THREE.Mesh(lampGeo, this.materials.white);
        lamp.position.set(0, 1.94, 0.46);
        hg.add(lamp);

        // Underside hair rim
        const hairBack = new THREE.Mesh(new THREE.BoxGeometry(0.74, 0.30, 0.12), mat);
        hairBack.position.set(0, 1.64, -0.33);
        const sideL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.25, 0.50), mat);
        sideL.position.set(-0.37, 1.68, 0.05);
        const sideR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.25, 0.50), mat);
        sideR.position.set(0.37, 1.68, 0.05);
        hg.add(hairBack, sideL, sideR);
      } else if (style === 'cap') {
        // Baseball cap
        const capMat = new THREE.MeshStandardMaterial({ color: new THREE.Color(this.options.clothesColor), roughness: 0.4 });
        const dome = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.26, 0.74), capMat);
        dome.position.set(0, 1.88, 0);
        hg.add(dome);

        const visor = new THREE.Mesh(new THREE.BoxGeometry(0.68, 0.05, 0.32), capMat);
        visor.position.set(0, 1.80, 0.46);
        hg.add(visor);

        const hairBack = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.35, 0.12), mat);
        hairBack.position.set(0, 1.68, -0.33);
        const sideburnL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.28, 0.35), mat);
        sideburnL.position.set(-0.38, 1.72, 0.10);
        const sideburnR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.28, 0.35), mat);
        sideburnR.position.set(0.38, 1.72, 0.10);
        hg.add(hairBack, sideburnL, sideburnR);
      } else if (style === 'beanie') {
        // Cozy folded beanie
        const beanieMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.6 });
        const dome = new THREE.Mesh(new THREE.BoxGeometry(0.78, 0.38, 0.76), beanieMat);
        dome.position.set(0, 1.90, 0);
        hg.add(dome);

        const fold = new THREE.Mesh(new THREE.BoxGeometry(0.82, 0.12, 0.80), beanieMat);
        fold.position.set(0, 1.75, 0);
        hg.add(fold);

        const hairBack = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.25, 0.10), mat);
        hairBack.position.set(0, 1.64, -0.33);
        hg.add(hairBack);
      } else if (style === 'twintails') {
        // Twin Tails (Female pigtails)
        const top = new THREE.Mesh(new THREE.BoxGeometry(0.78, 0.32, 0.74), mat);
        top.position.set(0, 1.86, -0.02);
        hg.add(top);

        const fringe = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.15, 0.14), mat);
        fringe.position.set(0, 1.86, 0.34);
        hg.add(fringe);

        const sideL = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.42, 0.68), mat);
        sideL.position.set(-0.38, 1.70, 0);
        const sideR = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.42, 0.68), mat);
        sideR.position.set(0.38, 1.70, 0);
        const back = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.52, 0.14), mat);
        back.position.set(0, 1.65, -0.34);
        hg.add(sideL, sideR, back);

        const tailL = new THREE.Mesh(new THREE.ConeGeometry(0.12, 0.55, 6), mat);
        tailL.position.set(-0.45, 1.55, 0.05);
        tailL.rotation.z = 0.25;
        const tailR = new THREE.Mesh(new THREE.ConeGeometry(0.12, 0.55, 6), mat);
        tailR.position.set(0.45, 1.55, 0.05);
        tailR.rotation.z = -0.25;
        hg.add(tailL, tailR);

        const tieL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.08, 0.08), this.materials.clothes);
        tieL.position.set(-0.40, 1.80, 0.05);
        const tieR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.08, 0.08), this.materials.clothes);
        tieR.position.set(0.40, 1.80, 0.05);
        hg.add(tieL, tieR);
      } else if (style === 'wavy') {
        // Long Wavy Hair (Female)
        const top = new THREE.Mesh(new THREE.BoxGeometry(0.80, 0.34, 0.76), mat);
        top.position.set(0, 1.87, -0.01);
        hg.add(top);

        const fringe = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.15, 0.15), mat);
        fringe.position.set(0, 1.86, 0.34);
        hg.add(fringe);

        const sideL = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.85, 0.50), mat);
        sideL.position.set(-0.39, 1.45, 0.08);
        const sideR = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.85, 0.50), mat);
        sideR.position.set(0.39, 1.45, 0.08);
        hg.add(sideL, sideR);

        const back = new THREE.Mesh(new THREE.BoxGeometry(0.78, 0.85, 0.18), mat);
        back.position.set(0, 1.45, -0.34);
        hg.add(back);
      } else if (style === 'quiff') {
        // Quiff / Pompadour (Male)
        const top = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.38, 0.72), mat);
        top.position.set(0, 1.90, -0.02);
        top.rotation.x = -0.12;
        hg.add(top);

        const sweep = new THREE.Mesh(new THREE.BoxGeometry(0.64, 0.22, 0.25), mat);
        sweep.position.set(0, 1.95, 0.30);
        hg.add(sweep);

        const sideL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.30, 0.65), mat);
        sideL.position.set(-0.38, 1.72, 0);
        const sideR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.30, 0.65), mat);
        sideR.position.set(0.38, 1.72, 0);
        const back = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.45, 0.14), mat);
        back.position.set(0, 1.68, -0.34);
        hg.add(sideL, sideR, back);
      } else if (style === 'buzz') {
        // Clean Buzz Cut (Male)
        const top = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.18, 0.72), mat);
        top.position.set(0, 1.84, 0);
        hg.add(top);

        const back = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.35, 0.10), mat);
        back.position.set(0, 1.72, -0.33);
        const sideL = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.28, 0.68), mat);
        sideL.position.set(-0.37, 1.76, 0);
        const sideR = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.28, 0.68), mat);
        sideR.position.set(0.37, 1.76, 0);
        hg.add(back, sideL, sideR);
      }
    }

    buildFeature(feature) {
      if (this.parts.featureGroup) {
        this.charGroup.remove(this.parts.featureGroup);
      }

      const fg = new THREE.Group();
      this.parts.featureGroup = fg;
      this.charGroup.add(fg);

      const isFem = this.options.gender === 'female';

      if (isFem) {
        // Feminine Accents: 'natural', 'ribbon', 'flower', 'headband'
        if (feature === 'ribbon') {
          // Cute hair ribbon on top of hair
          const knot = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.08, 0.08), this.materials.pink);
          knot.position.set(0.24, 1.96, 0.28);
          fg.add(knot);

          const wingL = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.10, 0.04), this.materials.pink);
          wingL.position.set(0.17, 1.98, 0.28);
          wingL.rotation.z = 0.4;
          const wingR = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.10, 0.04), this.materials.pink);
          wingR.position.set(0.31, 1.98, 0.28);
          wingR.rotation.z = -0.4;
          fg.add(wingL, wingR);
        } else if (feature === 'flower') {
          // Relief flower pin
          const center = new THREE.Mesh(new THREE.SphereGeometry(0.05, 8, 8), this.materials.yellow);
          center.position.set(-0.36, 1.84, 0.24);
          fg.add(center);

          for (let i = 0; i < 5; i++) {
            const angle = (i * Math.PI * 2) / 5;
            const petal = new THREE.Mesh(new THREE.SphereGeometry(0.04, 6, 6), this.materials.pink);
            petal.position.set(-0.36 + Math.cos(angle) * 0.07, 1.84 + Math.sin(angle) * 0.07, 0.24);
            fg.add(petal);
          }
        } else if (feature === 'headband') {
          // Sport / Safety headband across forehead
          const band = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.08, 0.72), this.materials.clothes);
          band.position.set(0, 1.78, 0.02);
          fg.add(band);
        }
      } else {
        // Male Features: 'clean', 'stubble', 'mustache', 'goatee'
        if (feature === 'stubble') {
          // 5 o'clock shadow on chin and lower jaw
          const shadow = new THREE.Mesh(new THREE.BoxGeometry(0.44, 0.18, 0.06), this.materials.stubble);
          shadow.position.set(0, 1.40, 0.35);
          fg.add(shadow);
        } else if (feature === 'mustache') {
          // Classic rescuer mustache above mouth
          const stacheGeo = new THREE.BoxGeometry(0.24, 0.06, 0.06);
          const stache = new THREE.Mesh(stacheGeo, this.materials.hair);
          stache.position.set(0, 1.51, 0.36);
          fg.add(stache);
        } else if (feature === 'goatee') {
          // Goatee (Mustache + Chin beard)
          const stacheGeo = new THREE.BoxGeometry(0.22, 0.05, 0.06);
          const stache = new THREE.Mesh(stacheGeo, this.materials.hair);
          stache.position.set(0, 1.51, 0.36);
          fg.add(stache);

          const chinGeo = new THREE.BoxGeometry(0.16, 0.12, 0.06);
          const chin = new THREE.Mesh(chinGeo, this.materials.hair);
          chin.position.set(0, 1.36, 0.36);
          fg.add(chin);
        }
      }
    }

    buildClothes(style) {
      if (this.parts.clothesGroup) {
        this.charGroup.remove(this.parts.clothesGroup);
      }

      const cg = new THREE.Group();
      this.parts.clothesGroup = cg;
      this.charGroup.add(cg);

      const mat = this.materials.clothes;
      const isFem = this.options.gender === 'female';
      const bodyW = isFem ? 0.62 : 0.70;
      const bodyH = isFem ? 0.73 : 0.76;
      const bodyD = isFem ? 0.43 : 0.47;
      const armX = isFem ? 0.45 : 0.49;

      // Body base
      const bodyGeo = new THREE.BoxGeometry(bodyW, bodyH, bodyD);
      const body = new THREE.Mesh(bodyGeo, mat);
      body.position.y = 0.95;
      cg.add(body);

      // Arms
      const isShortSleeve = (style === 'tee' || style === 'scrubs');
      const armGeo = new THREE.BoxGeometry(0.17, 0.65, 0.22);
      const armL = new THREE.Mesh(armGeo, isShortSleeve ? this.materials.skin : mat);
      armL.position.set(-armX, 0.92, 0);
      armL.rotation.z = 0.12;

      const armR = new THREE.Mesh(armGeo, isShortSleeve ? this.materials.skin : mat);
      armR.position.set(armX, 0.92, 0);
      armR.rotation.z = -0.12;
      cg.add(armL, armR);

      if (isShortSleeve) {
        // Sleeves on top of arms
        const sleeveGeo = new THREE.BoxGeometry(0.19, 0.24, 0.24);
        const slvL = new THREE.Mesh(sleeveGeo, mat);
        slvL.position.set(-armX, 1.13, 0);
        const slvR = new THREE.Mesh(sleeveGeo, mat);
        slvR.position.set(armX, 1.13, 0);
        cg.add(slvL, slvR);
      }

      // Specific Outfit Details
      if (style === 'vest') {
        // Rescue Vest: Dual reflective stripes + zipper
        const stripe1 = new THREE.Mesh(new THREE.BoxGeometry(bodyW + 0.02, 0.10, bodyD + 0.02), this.materials.reflective);
        stripe1.position.y = 1.05;
        const stripe2 = new THREE.Mesh(new THREE.BoxGeometry(bodyW + 0.02, 0.10, bodyD + 0.02), this.materials.reflective);
        stripe2.position.y = 0.80;
        cg.add(stripe1, stripe2);

        const zip = new THREE.Mesh(new THREE.BoxGeometry(0.04, bodyH + 0.02, bodyD + 0.03), this.materials.dark);
        zip.position.y = 0.95;
        cg.add(zip);
      } else if (style === 'jacket') {
        // Weather Storm Jacket: Collar, pockets, reflective zip
        const collar = new THREE.Mesh(new THREE.BoxGeometry(bodyW * 0.85, 0.14, bodyD + 0.02), mat);
        collar.position.set(0, 1.34, 0);
        cg.add(collar);

        const zip = new THREE.Mesh(new THREE.BoxGeometry(0.04, bodyH + 0.02, bodyD + 0.02), this.materials.reflective);
        zip.position.y = 0.95;
        cg.add(zip);

        const pL = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.16, 0.04), this.materials.dark);
        pL.position.set(-bodyW * 0.3, 0.82, bodyD / 2 + 0.02);
        const pR = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.16, 0.04), this.materials.dark);
        pR.position.set(bodyW * 0.3, 0.82, bodyD / 2 + 0.02);
        cg.add(pL, pR);
      } else if (style === 'raincoat') {
        // Emergency Rain Poncho: Flared waterproof hem + storm cowl
        const flareGeo = new THREE.BoxGeometry(bodyW + 0.10, 0.32, bodyD + 0.10);
        const flare = new THREE.Mesh(flareGeo, mat);
        flare.position.y = 0.70;
        cg.add(flare);

        const cowl = new THREE.Mesh(new THREE.BoxGeometry(bodyW * 0.92, 0.18, bodyD + 0.06), mat);
        cowl.position.set(0, 1.35, 0);
        cg.add(cowl);

        // Reflective edge band
        const edge = new THREE.Mesh(new THREE.BoxGeometry(bodyW + 0.12, 0.06, bodyD + 0.12), this.materials.reflective);
        edge.position.y = 0.56;
        cg.add(edge);
      } else if (style === 'wetsuit') {
        // Flood Rescue Diver Wetsuit: Scuba harness, belt & oxygen buckle
        const harnessMat = new THREE.MeshStandardMaterial({ color: 0x0284c7, roughness: 0.3 });
        const belt = new THREE.Mesh(new THREE.BoxGeometry(bodyW + 0.03, 0.14, bodyD + 0.03), this.materials.dark);
        belt.position.y = 0.64;
        cg.add(belt);

        const strapL = new THREE.Mesh(new THREE.BoxGeometry(0.10, bodyH, bodyD + 0.02), harnessMat);
        strapL.position.set(-bodyW * 0.28, 0.95, 0);
        const strapR = new THREE.Mesh(new THREE.BoxGeometry(0.10, bodyH, bodyD + 0.02), harnessMat);
        strapR.position.set(bodyW * 0.28, 0.95, 0);
        cg.add(strapL, strapR);

        const buckle = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.12, bodyD + 0.05), this.materials.reflective);
        buckle.position.set(0, 0.64, 0);
        cg.add(buckle);
      } else if (style === 'parka') {
        // High-Vis Winter Storm Parka: Thick padded collar & double reflective bands
        const collar = new THREE.Mesh(new THREE.BoxGeometry(bodyW + 0.04, 0.20, bodyD + 0.06), this.materials.dark);
        collar.position.set(0, 1.35, 0);
        cg.add(collar);

        const stripe1 = new THREE.Mesh(new THREE.BoxGeometry(bodyW + 0.02, 0.08, bodyD + 0.02), this.materials.reflective);
        stripe1.position.y = 1.08;
        const stripe2 = new THREE.Mesh(new THREE.BoxGeometry(bodyW + 0.02, 0.08, bodyD + 0.02), this.materials.reflective);
        stripe2.position.y = 0.72;
        cg.add(stripe1, stripe2);

        // Arm reflective bands
        const armBandL = new THREE.Mesh(new THREE.BoxGeometry(0.20, 0.08, 0.24), this.materials.reflective);
        armBandL.position.set(-armX, 0.95, 0);
        const armBandR = new THREE.Mesh(new THREE.BoxGeometry(0.20, 0.08, 0.24), this.materials.reflective);
        armBandR.position.set(armX, 0.95, 0);
        cg.add(armBandL, armBandR);
      } else if (style === 'scrubs') {
        // Medical Relief Scrubs: V-neck + Red Cross / White Cross badge
        const crossH = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.05, 0.03), this.materials.white);
        crossH.position.set(-bodyW * 0.26, 1.08, bodyD / 2 + 0.02);
        const crossV = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.14, 0.03), this.materials.white);
        crossV.position.set(-bodyW * 0.26, 1.08, bodyD / 2 + 0.02);
        cg.add(crossH, crossV);

        const vNeck = new THREE.Mesh(new THREE.BoxGeometry(0.20, 0.16, 0.04), this.materials.skin);
        vNeck.position.set(0, 1.30, bodyD / 2);
        cg.add(vNeck);
      } else if (style === 'overalls') {
        // Volunteer Overalls / Dungarees: Front bib, straps & brass buckles
        const bib = new THREE.Mesh(new THREE.BoxGeometry(bodyW * 0.72, 0.44, bodyD + 0.02), this.materials.dark);
        bib.position.set(0, 0.80, 0);
        cg.add(bib);

        const strapL = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.48, bodyD + 0.03), this.materials.dark);
        strapL.position.set(-bodyW * 0.26, 1.15, 0);
        const strapR = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.48, bodyD + 0.03), this.materials.dark);
        strapR.position.set(bodyW * 0.26, 1.15, 0);
        cg.add(strapL, strapR);

        const bL = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.08, bodyD + 0.05), this.materials.brass);
        bL.position.set(-bodyW * 0.26, 0.98, 0);
        const bR = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.08, bodyD + 0.05), this.materials.brass);
        bR.position.set(bodyW * 0.26, 0.98, 0);
        cg.add(bL, bR);

        // Center front pocket
        const pocket = new THREE.Mesh(new THREE.BoxGeometry(0.28, 0.18, 0.03), this.materials.dark);
        pocket.position.set(0, 0.78, bodyD / 2 + 0.02);
        cg.add(pocket);
      } else if (style === 'uniform') {
        // Tactical Responder Uniform: Badge, belt & shoulder radio
        const badge = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.14, 0.04), this.materials.white);
        badge.position.set(-bodyW * 0.28, 1.12, bodyD / 2 + 0.02);
        cg.add(badge);

        const belt = new THREE.Mesh(new THREE.BoxGeometry(bodyW + 0.03, 0.12, bodyD + 0.03), this.materials.dark);
        belt.position.y = 0.62;
        cg.add(belt);

        const buckle = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.14, bodyD + 0.05), this.materials.reflective);
        buckle.position.y = 0.62;
        cg.add(buckle);

        // Shoulder radio
        const radio = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.16, 0.10), this.materials.dark);
        radio.position.set(bodyW * 0.32, 1.34, 0.10);
        cg.add(radio);
      } else if (style === 'hoodie') {
        // Relief Shelter Hoodie: Cowl on back + front kangaroo pocket
        const hood = new THREE.Mesh(new THREE.BoxGeometry(bodyW * 0.88, 0.32, 0.22), mat);
        hood.position.set(0, 1.25, -bodyD / 2 - 0.05);
        cg.add(hood);

        const pocket = new THREE.Mesh(new THREE.BoxGeometry(bodyW * 0.65, 0.22, 0.08), mat);
        pocket.position.set(0, 0.82, bodyD / 2 + 0.02);
        cg.add(pocket);
      }
    }

    updateConfig(newOptions) {
      const genderChanged = newOptions.gender && newOptions.gender !== this.options.gender;
      Object.assign(this.options, newOptions);

      if (newOptions.skinColor && this.materials.skin) {
        this.materials.skin.color.set(newOptions.skinColor);
      }
      if (newOptions.hairColor && this.materials.hair) {
        this.materials.hair.color.set(newOptions.hairColor);
      }
      if (newOptions.clothesColor && this.materials.clothes) {
        this.materials.clothes.color.set(newOptions.clothesColor);
      }

      if (genderChanged) {
        this.buildBaseBody();
      }

      if (genderChanged || newOptions.hair) {
        this.buildHair(this.options.hair);
      }
      if (genderChanged || newOptions.clothes) {
        this.buildClothes(this.options.clothes);
      }
      if (genderChanged || newOptions.feature) {
        this.buildFeature(this.options.feature);
      }

      this.render();
    }

    handleResize() {
      this.resize();
    }

    resize() {
      if (!this.canvas || !this.renderer || !this.camera) return;
      const parent = this.canvas.parentElement;
      const width = (parent && parent.clientWidth > 0 ? parent.clientWidth : this.canvas.clientWidth) || 340;
      const height = (parent && parent.clientHeight > 0 ? parent.clientHeight : this.canvas.clientHeight) || 320;
      if (width > 0 && height > 0) {
        const aspect = width / height;
        this.camera.aspect = aspect;

        // Dynamic responsive reframing to guarantee complete visibility (pedestal to top of helmet/hair)
        // on every screen width (320px, 375px, 390px, 430px, 768px, desktop):
        if (aspect < 0.85) {
          // Narrow mobile screen: expand FOV proportionally so horizontal bounds (hands) don't clip
          this.camera.fov = Math.min(42, (34 / aspect) * 0.86);
        } else if (aspect < 1.15) {
          this.camera.fov = 35;
        } else if (aspect > 1.6) {
          // Wide / landscape container: keep vertical FOV calibrated for head and pedestal
          this.camera.fov = 36;
        } else {
          this.camera.fov = 34;
        }

        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height, false);
        this.render();
      }
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
        this.charGroup.rotation.y += delta * 0.015;
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

      // Gentle natural breathing that stays grounded on the pedestal surface (y >= 0)
      if (this.charGroup) {
        this.charGroup.position.y = (Math.sin(time * 2.2) * 0.5 + 0.5) * 0.02;

        if (this.isDragging) {
          // Handled in mousemove
        } else if (Math.abs(this.rotationVelocity) > 0.0005) {
          this.charGroup.rotation.y += this.rotationVelocity;
          this.rotationVelocity *= 0.92;
        } else if (this.options.autoRotate) {
          this.charGroup.rotation.y += 0.005;
        }
      }

      this.render();
    }

    render() {
      if (this.renderer && this.scene && this.camera) {
        this.renderer.render(this.scene, this.camera);
      }
    }

    getSnapshot(quality = 0.85) {
      if (!this.renderer) return '';
      const currentRot = this.charGroup.rotation.y;
      this.charGroup.rotation.y = 0.32; // pleasing 3/4 angle
      this.render();
      const dataUrl = this.canvas.toDataURL('image/jpeg', quality);
      this.charGroup.rotation.y = currentRot;
      return dataUrl;
    }

    getConfig() {
      return {
        gender: this.options.gender,
        feature: this.options.feature,
        hair: this.options.hair,
        hairColor: this.options.hairColor,
        clothes: this.options.clothes,
        clothesColor: this.options.clothesColor,
        skinColor: this.options.skinColor
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

  window.Character3D = Character3D;
})();
